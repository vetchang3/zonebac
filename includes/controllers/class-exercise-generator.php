<?php

class Zonebac_Exercise_Generator
{
    public function __construct()
    {
        // Hook pour le traitement asynchrone des exercices
        add_action('zb_async_exercise_event', [$this, 'process_exercise_generation'], 10, 2);

        add_action('admin_init', [$this, 'debug_force_process']);
    }

    /**
     * Gère la soumission du formulaire du générateur d'exercices
     */
    public function handle_exercise_request()
    {
        check_admin_referer('zb_ex_gen_action');

        global $wpdb;
        $notion_id = intval($_POST['notion_id']);
        $count     = intval($_POST['nb_questions']);

        if (!$notion_id) return;

        // Insertion dans la file d'attente des exercices
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'zb_exercise_jobs',
            [
                'notion_id'  => $notion_id,
                'count'      => $count,
                'status'     => 'pending',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s']
        );

        if ($inserted) {
            $job_id = $wpdb->insert_id;
            // On lance le traitement immédiatement (similaire aux questions)
            wp_schedule_single_event(time(), 'zb_async_exercise_event', [$job_id, $notion_id]);

            $lms = new ZonebacLMS();
            $lms->dispatch_next_job();

            wp_redirect(admin_url('admin.php?page=zonebac-ex-gen&scheduled=1&success=1'));
            exit;
        }
    }

    /**
     * Traitement réel via l'API DeepSeek
     */
    public function process_exercise_generation($job_id, $notion_id)
    {
        global $wpdb;
        $table_jobs = $wpdb->prefix . 'zb_exercise_jobs';

        // 1. DÉBUT DU TRAITEMENT
        $wpdb->update($table_jobs, ['status' => 'processing'], ['id' => $job_id]);
        $job_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_jobs WHERE id = %d", $job_id));

        if (!$job_data) return;

        $params = [];
        $deepseek = new Zonebac_DeepSeek_API();

        // 2. LOGIQUE CONDITIONNELLE : EXTRACTION VS GÉNÉRATION
        if ($notion_id == 0) {
            // --- MODE EXTRACTION D'ARCHIVE ---
            $params = json_decode($job_data->params, true);
            $params['mode'] = 'extraction';

            // On récupère le nom de la matière pour le prompt
            $term = get_term($params['matiere_id'], 'matiere');
            $params['matiere'] = $term ? $term->name : 'Scientifique';

            error_log("ZONEBAC: Transformation d'un exercice extrait (Fichier: " . $params['file_reference'] . ")");
        } else {
            // --- MODE GÉNÉRATION CLASSIQUE (MAILLAGE) --- [cite: 2025-11-16]
            $related_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT related_notion_id FROM {$wpdb->prefix}zb_notion_relations WHERE notion_id = %d",
                $notion_id
            ));

            $extra_notions = [];
            if (!empty($related_ids)) {
                foreach ($related_ids as $rid) {
                    $title = get_the_title($rid);
                    if ($title) $extra_notions[] = $title;
                }
            }

            $params = [
                'mode'          => 'generation',
                'count'         => $job_data->count,
                'notion'        => get_the_title($notion_id),
                'extra_notions' => $extra_notions,
                'classe'        => wp_get_post_terms($notion_id, 'classe')[0]->name ?? 'Terminale',
                'matiere'       => wp_get_post_terms($notion_id, 'matiere')[0]->name ?? '',
                'chapitre'      => wp_get_post_terms($notion_id, 'chapitre')[0]->name ?? '',
                'origin'        => 'IA Zonebac', // Valeurs par défaut pour la cohérence JSON
                'file_reference' => 'Generated',
                'f' => 30,
                'm' => 40,
                'd' => 30
            ];

            error_log("ZONEBAC: Génération IA pour la notion " . $params['notion']);
        }

        // 3. APPEL API
        $result_json = $deepseek->generate_exercise_batch($params);

        if (!$result_json) {
            $wpdb->update($table_jobs, ['status' => 'failed'], ['id' => $job_id]);
            return;
        }

        // 4. NETTOYAGE ET INSERTION
        $clean_json = preg_replace('/^```json|```$/m', '', $result_json);
        $exercise = json_decode(trim($clean_json), true);

        if ($exercise && isset($exercise['questions'])) {
            $total_points = 0;
            $difficulty_counts = ['Facile' => 0, 'Moyen' => 0, 'Difficile' => 0];

            foreach ($exercise['questions'] as &$q) {
                $diff = ucfirst(strtolower($q['difficulty'] ?? 'Moyen'));
                $difficulty_counts[$diff]++;

                $p = ($diff === 'Facile') ? 1 : (($diff === 'Difficile') ? 3 : 2);
                $q['points'] = $p;
                $total_points += $p;
            }

            arsort($difficulty_counts);
            $global_diff = key($difficulty_counts);

            $wpdb->insert(
                $wpdb->prefix . 'zb_exercises',
                [
                    'notion_id'     => $notion_id,
                    'title'         => sanitize_text_field($exercise['exercise_title']),
                    'subject_text'  => wp_kses_post($exercise['subject_text']),
                    'exercise_data' => json_encode($exercise['questions'], JSON_UNESCAPED_UNICODE),
                    'total_points'  => $total_points, // Stockage du score [cite: 2026-03-21]
                    'difficulty'    => $global_diff,   // Stockage de la difficulté [cite: 2026-03-21]
                    'created_at'    => current_time('mysql')
                ]
            );
            $wpdb->update($table_jobs, ['status' => 'completed'], ['id' => $job_id]);
        } else {
            error_log("ZONEBAC ERROR: JSON invalide ou questions manquantes pour le job " . $job_id);
            $wpdb->update($table_jobs, ['status' => 'failed'], ['id' => $job_id]);
        }

        do_action('zb_job_completed');
    }

    public function debug_force_process()
    {
        if (isset($_GET['force_ex_job'])) {
            $job_id = intval($_GET['force_ex_job']);
            global $wpdb;
            $notion_id = $wpdb->get_var($wpdb->prepare("SELECT notion_id FROM {$wpdb->prefix}zb_exercise_jobs WHERE id = %d", $job_id));

            error_log("ZONEBAC DEBUG: Lancement FORCÉ du job d'exercice " . $job_id);
            $this->process_exercise_generation($job_id, $notion_id);

            wp_redirect(admin_url('admin.php?page=zonebac-ex-gen&force_done=1'));
            exit;
        }
    }
}
