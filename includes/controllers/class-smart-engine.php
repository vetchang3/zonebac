<?php
if (!defined('ABSPATH')) exit;

class Zonebac_Smart_Engine
{

    /**
     * Analyse une matière et retourne les statistiques (STATIQUE)
     */
    public static function get_priority_notions($matiere_id, $threshold_n = 50)
    {
        global $wpdb;

        // Appel de la méthode statique de liaison
        $notions = self::get_all_notions_by_matiere($matiere_id);
        $priority_list = [];

        if (empty($notions)) return [];

        foreach ($notions as $notion) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}zb_exercises WHERE notion_id = %d",
                $notion->ID
            ));

            $gap = $threshold_n - $count;

            $priority_list[] = [
                'id'    => $notion->ID,
                'name'  => $notion->post_title,
                'count' => (int)$count,
                'gap'   => ($gap > 0) ? $gap : 0
            ];
        }

        usort($priority_list, function ($a, $b) {
            return $b['gap'] <=> $a['gap'];
        });

        return $priority_list;
    }

    /**
     * Dispatcher Automatique (STATIQUE)
     */
    public static function run_auto_dispatcher()
    {
        // Récupération des réglages
        $settings = Zonebac_Settings_Model::get_settings();

        // Si l'interrupteur n'est pas activé, on arrête tout immédiatement [cite: 2025-11-16]
        if (empty($settings['enable_smart_dispatcher']) || $settings['enable_smart_dispatcher'] !== 'yes') {
            // error_log("Zonebac: Dispatcher ignoré (Mode OFF)"); 
            return;
        }


        global $wpdb;

        $schedules = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}zb_smart_schedules");

        foreach ($schedules as $sched) {
            // APPEL DIRECT SANS "NEW"
            $priorities = self::get_priority_notions($sched->matiere_id, $sched->threshold_n);

            if (!empty($priorities)) {
                $top_priority = $priorities[0];
                if ($top_priority['gap'] > 0) {
                    $wpdb->insert($wpdb->prefix . 'zb_exercise_jobs', [
                        'notion_id'  => $top_priority['id'],
                        'count'      => 10,
                        'status'     => 'pending',
                        'created_at' => current_time('mysql')
                    ]);
                }
            }
        }
    }

    /**
     * Récupération des notions (STATIQUE)
     */
    private static function get_all_notions_by_matiere($matiere_id)
    {
        global $wpdb;

        // Utilisation de la clé validée par le diagnostic
        $chapitre_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'parent_id' AND meta_value = %d",
            $matiere_id
        ));

        if (empty($chapitre_ids)) return [];

        return get_posts([
            'post_type'      => 'notion',
            'posts_per_page' => -1,
            'tax_query'      => [[
                'taxonomy' => 'chapitre',
                'field'    => 'term_id',
                'terms'    => $chapitre_ids,
                'operator' => 'IN'
            ]],
            'orderby' => 'title',
            'order'   => 'ASC'
        ]);
    }

    public static function map_notions_relations_with_ai()
    {
        global $wpdb;

        // 1. Récupérer TOUTES les notions existantes pour servir de référentiel [cite: 2025-11-16]
        $all_notions = get_posts(['post_type' => 'notion', 'numberposts' => -1, 'post_status' => 'publish']);
        $ref_list = implode(', ', wp_list_pluck($all_notions, 'post_title'));

        // 2. Cibler uniquement les notions qui n'ont pas encore de liens (notre cache SQL)
        $table_rel = $wpdb->prefix . 'zb_notion_relations';
        $to_scan = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} 
        WHERE post_type = 'notion' AND post_status = 'publish' 
        AND ID NOT IN (SELECT notion_id FROM $table_rel) LIMIT 5");

        foreach ($to_scan as $notion) {
            $prompt = "Voici une notion cible : '{$notion->post_title}'. 
        Choisis EXCLUSIVEMENT dans cette liste de notions existantes les 2 plus complémentaires pour un exercice de synthèse :
        [$ref_list].
        Réponds au format JSON : {\"relations\": [{\"notion_suggeree\": \"NOM_EXACT\"}]}";

            $response = Zonebac_DeepSeek_API::call_deepseek_raw($prompt);
            if ($response) {
                $data = json_decode(preg_replace('/^```json|```$/m', '', $response), true);
                self::save_ai_relations($notion->ID, $data);
            }
        }
    }

    private static function save_ai_relations($notion_id, $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_notion_relations';

        if (!isset($data['relations']) || !is_array($data['relations'])) {
            error_log("Zonebac Debug: Format JSON invalide reçu de l'IA.");
            return;
        }

        foreach ($data['relations'] as $rel) {
            $suggested_name = sanitize_text_field($rel['notion_suggeree']);

            // Recherche plus souple (LIKE) pour trouver la notion [cite: 2025-11-16]
            $related_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'notion' 
             AND post_status = 'publish'
             AND post_title LIKE %s 
             LIMIT 1",
                '%' . $wpdb->esc_like($suggested_name) . '%'
            ));

            if ($related_id && $related_id != $notion_id) {
                $wpdb->replace($table, [
                    'notion_id'         => $notion_id,
                    'related_notion_id' => $related_id,
                    'strength'          => 5
                ]);
                error_log("Zonebac Mapping: LIEN CRÉÉ entre " . get_the_title($notion_id) . " et " . get_the_title($related_id));
            } else {
                error_log("Zonebac Mapping: Notion suggérée '$suggested_name' non trouvée en base.");
            }
        }
    }

    /**
     * Orchestre le découpage d'un PDF en exercices distincts
     */
    public static function process_file_splitting($ingestion_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_file_ingestion';
        $file = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $ingestion_id));

        if (!$file || !file_exists($file->file_path)) {
            $wpdb->update($table, ['status' => 'failed'], ['id' => $ingestion_id]);
            return;
        }

        $wpdb->update($table, ['status' => 'processing'], ['id' => $ingestion_id]);

        // 1. Préparation du fichier pour l'IA (Vision)
        $file_data = base64_encode(file_get_contents($file->file_path));
        $mime_type = mime_content_type($file->file_path);

        // 2. Prompt de découpage visuel intelligent [cite: 2025-11-16]
        $prompt = "Tu es un expert en numérisation d'archives pédagogiques de %s. 
        Analyse visuellement ce document (Origine : %s).
        
        MISSIONS :
        1. Identifie chaque exercice distinct présent sur les pages.
        2. Extrais le texte intégral de chaque exercice avec une précision chirurgicale.
        3. Ignore absolument les ratures, ronds, ou notes écrites à la main par des élèves.
        4. Ignore les publicités ou en-têtes de l'époque.
        
        RÉPONDS EXCLUSIVEMENT en JSON : {\"exercices\": [\"Texte exercice 1\", \"Texte exercice 2\"]}";

        $params = [
            'prompt' => sprintf($prompt, $file->origin_info, $file->origin_info),
            'file_data' => $file_data,
            'mime_type' => $mime_type
        ];

        // On appelle une nouvelle méthode Vision que nous allons créer
        $response = Zonebac_DeepSeek_API::call_deepseek_vision($params);

        if ($response) {
            $data = json_decode(preg_replace('/^```json|```$/m', '', $response), true);
            if (!empty($data['exercices'])) {
                foreach ($data['exercices'] as $raw_ex) {
                    self::create_extraction_job($file->id, $raw_ex, $file->matiere_id, $file->origin_info, $file->file_name);
                }
                $wpdb->update($table, [
                    'status' => 'completed',
                    'total_exercises_found' => count($data['exercices'])
                ], ['id' => $ingestion_id]);
            }
        } else {
            $wpdb->update($table, ['status' => 'failed'], ['id' => $ingestion_id]);
        }
    }

    private static function create_extraction_job($ingestion_id, $raw_text, $matiere_id, $origin, $file_ref)
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'zb_exercise_jobs', [
            'notion_id' => 0, // 0 indique que c'est une extraction externe
            'status'    => 'pending',
            'params'    => json_encode([
                'mode'           => 'extraction',
                'raw_content'    => $raw_text,
                'matiere_id'     => $matiere_id,
                'origin'         => $origin,
                'file_reference' => $file_ref
            ]),
            'created_at' => current_time('mysql')
        ]);
    }

    public static function run_ingestion_dispatcher()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_file_ingestion';

        // Sécurité : On vérifie le Toggle ON/OFF
        $settings = Zonebac_Settings_Model::get_settings();
        if (($settings['enable_smart_dispatcher'] ?? 'no') !== 'yes') return;

        // Récupérer le plus vieux fichier en attente
        $file = $wpdb->get_row("SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1");

        if ($file) {
            error_log("ZONEBAC: Début du découpage pour " . $file->file_name);
            self::process_file_splitting($file->id);
        }
    }
}
