<?php

class Zonebac_Question_Generator
{
    public function __construct()
    {
        // Hook pour le cron
        add_action('zb_process_generation_cron', [$this, 'process_generation_queue'], 10, 2);
    }


    /**
     * Récupère la hiérarchie pour le formulaire
     */
    public static function get_form_data()
    {
        return [
            'classes'   => get_terms(['taxonomy' => 'classe', 'hide_empty' => false]),
            'matieres'  => get_terms(['taxonomy' => 'matiere', 'hide_empty' => false]),
            'chapitres' => get_terms(['taxonomy' => 'chapitre', 'hide_empty' => false]),
            'notions'   => get_posts(['post_type' => 'notion', 'numberposts' => -1])
        ];
    }

    public function handle_generation_request()
    {
        check_admin_referer('zb_gen_action');

        global $wpdb;
        $notion_id = intval($_POST['notion_id']);
        $count = intval($_POST['nb_questions']);

        // Sécurité : Vérifier que l'ID existe
        if (!$notion_id) {
            return false;
        }

        // Insertion avec formatage explicite pour éviter les erreurs SQL
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'zb_generation_jobs',
            array(
                'notion_id' => $notion_id,
                'count'     => $count,
                'status'    => 'pending',
                'created_at' => current_time('mysql') // Force la date locale WP
            ),
            array('%d', '%d', '%s', '%s')
        );

        if ($inserted) {
            // 2. On appelle le dispatcher de la classe principale
            $lms = new ZonebacLMS();
            $lms->dispatch_next_job();

            wp_redirect(admin_url('admin.php?page=zonebac-questions&scheduled=1'));
            exit;

        } else {
            // Log l'erreur SQL si l'insertion échoue
            error_log("ZONEBAC SQL ERROR: " . $wpdb->last_error);

            wp_redirect(admin_url('admin.php?page=zonebac-questions&gen_status=error'));
            exit;

        }
    }

    public function process_generation_queue($job_id, $notion_id)
    {
        global $wpdb;
        $table_jobs = $wpdb->prefix . 'zb_generation_jobs';


        // Ajoutez ceci pour forcer l'affichage des erreurs durant le développement
        @ini_set('display_errors', 1);
        error_log("ZONEBAC START: Lancement du job $job_id");

        $deepseek = new Zonebac_DeepSeek_API();

        // On récupère les paramètres nécessaires pour votre prompt expert
        $params = [
            'count'    => $wpdb->get_var($wpdb->prepare("SELECT count FROM $table_jobs WHERE id = %d", $job_id)),
            'notion'   => get_the_title($notion_id),
            'content'  => get_post_field('post_content', $notion_id),
            'classe'   => wp_get_post_terms($notion_id, 'classe')[0]->name ?? 'Terminale',
            'matiere'  => wp_get_post_terms($notion_id, 'matiere')[0]->name ?? '',
            'chapitre' => wp_get_post_terms($notion_id, 'chapitre')[0]->name ?? '',
            'type'     => 'QCM',
            'f'        => 30,
            'm'        => 40,
            'd'        => 30
        ];

        $result = $deepseek->generate_questions_batch($params);

        // NETTOYAGE DU JSON (Si la réponse contient des balises Markdown)
        if (is_string($result)) {
            $result = preg_replace('/^```json|```$/m', '', $result);
            $result = json_decode(trim($result), true);
        }

        if ($result && isset($result['questions']) && is_array($result['questions'])) {
            foreach ($result['questions'] as $q) {
                $wpdb->insert($wpdb->prefix . 'zb_questions', [
                    'notion_id'     => $notion_id,
                    'difficulty'    => $q['difficulty'] ?? 'Moyen',
                    'points'        => 1,
                    'question_data' => json_encode($q, JSON_UNESCAPED_UNICODE), // Préserve LaTeX et accents
                    'status'        => 'completed'
                ]);
            }
            $wpdb->update($table_jobs, ['status' => 'completed'], ['id' => $job_id]);
        } else {
            $wpdb->update($table_jobs, ['status' => 'failed'], ['id' => $job_id]);
            error_log("ZONEBAC AI ERROR: Échec parsing JSON pour Job " . $job_id);
        }

        if ($result && isset($result['questions'])) {
            $wpdb->update($table_jobs, ['status' => 'completed'], ['id' => $job_id]);
        } else {
            $wpdb->update($table_jobs, ['status' => 'failed'], ['id' => $job_id]);
        }

        // DÉCLENCHEUR UNIQUE : On libère la file d'attente quoi qu'il arrive
        do_action('zb_job_completed');
    }

    private function save_to_bank($notion_id, $json_data)
    {
        global $wpdb;
        $decoded = json_decode($json_data, true);
        $table = $wpdb->prefix . 'zb_questions';

        if (isset($decoded['questions'])) {
            foreach ($decoded['questions'] as $q) {
                // Attribution des points selon la difficulté
                $points = ($q['difficulty'] === 'Difficile') ? 5 : (($q['difficulty'] === 'Moyen') ? 3 : 1);

                $wpdb->insert($table, [
                    'notion_id'     => $notion_id,
                    'difficulty'    => $q['difficulty'],
                    'points'        => $points,
                    'question_data' => json_encode($q),
                    'status'        => 'published'
                ]);
            }
        }
    }
}
