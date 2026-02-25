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
        $wpdb->update($table_jobs, ['status' => 'processing'], ['id' => $job_id]);

        $deepseek = new Zonebac_DeepSeek_API();

        error_log("ZONEBAC: Appel API DeepSeek pour l'exercice du job " . $job_id);

        // Préparation des paramètres pour le prompt
        $params = [
            'count'    => $wpdb->get_var($wpdb->prepare("SELECT count FROM $table_jobs WHERE id = %d", $job_id)),
            'notion'   => get_the_title($notion_id),
            'classe'   => wp_get_post_terms($notion_id, 'classe')[0]->name ?? 'Terminale',
            'matiere'  => wp_get_post_terms($notion_id, 'matiere')[0]->name ?? '',
            'chapitre' => wp_get_post_terms($notion_id, 'chapitre')[0]->name ?? '',
            'f' => 30,
            'm' => 40,
            'd' => 30 // Ratios de difficulté demandés
        ];

        $result_json = $deepseek->generate_exercise_batch($params);
        if (!$result_json) {
            error_log("ZONEBAC ERROR: L'API n'a rien renvoyé pour le job " . $job_id);
            $wpdb->update($table_jobs, ['status' => 'failed'], ['id' => $job_id]);
            return;
        }

        // Nettoyage et décodage
        if (is_string($result_json)) {
            $result_json = preg_replace('/^```json|```$/m', '', $result_json);
            $exercise = json_decode(trim($result_json), true);
        }

        if ($exercise && isset($exercise['questions'])) {
            // Insertion de l'exercice complet
            $wpdb->insert(
                $wpdb->prefix . 'zb_exercises',
                [
                    'notion_id'     => $notion_id,
                    'title'         => sanitize_text_field($exercise['exercise_title']),
                    'subject_text'  => wp_kses_post($exercise['subject_text']),
                    'exercise_data' => json_encode($exercise['questions'], JSON_UNESCAPED_UNICODE),
                    'created_at'    => current_time('mysql')
                ]
            );

            $wpdb->update($table_jobs, ['status' => 'completed'], ['id' => $job_id]);
        } else {
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
