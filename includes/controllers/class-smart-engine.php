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
}
