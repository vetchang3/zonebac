<?php

/**
 * Plugin Name: Zonebac LMS
 * Version: 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Chargement des Modèles
require_once plugin_dir_path(__FILE__) . 'includes/models/class-cpt-notion.php';
require_once plugin_dir_path(__FILE__) . 'includes/models/class-settings-model.php';
require_once plugin_dir_path(__FILE__) . 'includes/models/class-db-manager.php';

// Chargement des Contrôleurs
require_once plugin_dir_path(__FILE__) . 'includes/controllers/class-admin-controller.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/class-import-controller.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/class-deepseek-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/class-question-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/class-question-meta-box.php';
new Zonebac_Question_Meta_Box();

require_once plugin_dir_path(__FILE__) . 'includes/controllers/class-exercise-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/class-smart-engine.php'; // AJOUTE CETTE LIGNE ICI

class ZonebacLMS
{
    public function __construct()
    {
        new Zonebac_CPT_Notion();

        register_activation_hook(__FILE__, ['Zonebac_DB_Manager', 'create_tables']);

        $admin_controller = new Zonebac_Admin_Controller();

        if (is_admin()) {
            new Zonebac_Import_Controller();
            // On stocke l'instance pour pouvoir appeler ses méthodes
            $generator = new Zonebac_Question_Generator();

            // AJOUTER CES DEUX LIGNES :
            // Elles font le pont entre le formulaire et la méthode de traitement
            add_action('admin_post_zb_do_generation', [$generator, 'handle_generation_request']);

            $ex_generator = new Zonebac_Exercise_Generator();
            add_action('admin_post_zb_do_exercise_generation', [$ex_generator, 'handle_exercise_request']);
        }

        add_action('zb_async_generation_event', [$this, 'zb_execute_background_generation']);

        // On écoute la fin d'un traitement pour tenter de lancer le suivant
        add_action('zb_job_completed', [$this, 'dispatch_next_job']);

        // On garde votre cron de secours mais on le pointe vers dispatch_next_job
        if (!wp_next_scheduled('zb_check_pending_jobs_cron')) {
            wp_schedule_event(time(), 'hourly', 'zb_check_pending_jobs_cron');
        }
        add_action('zb_check_pending_jobs_cron', [$this, 'dispatch_next_job']);


        add_filter('query_vars', function ($vars) {
            $vars[] = 'zb_question_id';
            return $vars;
        });

        add_action('zb_async_exercise_event', [$this, 'zb_execute_background_exercise'], 10, 2);

        // Planification de l'événement personnalisé Zonebac
        if (!wp_next_scheduled('zb_smart_cron_hook')) {
            wp_schedule_event(time(), 'hourly', 'zb_smart_cron_hook');
        }

        // Planification de l'événement personnalisé Zonebac
        if (!wp_next_scheduled('zb_smart_cron_hook')) {
            wp_schedule_event(time(), 'hourly', 'zb_smart_cron_hook');
        }
        add_action('zb_smart_cron_hook', ['Zonebac_Smart_Engine', 'run_auto_dispatcher']);
    }
    public static function run_auto_dispatcher()
    {
        global $wpdb;

        // 1. Récupérer tous les plannings actifs
        $schedules = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}zb_smart_schedules");

        foreach ($schedules as $sched) {
            // 2. Analyser les notions de cette matière
            $priorities = Zonebac_Smart_Engine::get_priority_notions($sched->matiere_id, $sched->threshold_n);


            if (!empty($priorities)) {
                // 3. Prendre la notion avec le Gap le plus élevé
                $top_priority = $priorities[0];

                if ($top_priority['gap'] > 0) {
                    // 4. Créer un job de génération automatique
                    $wpdb->insert($wpdb->prefix . 'zb_exercise_jobs', [
                        'notion_id'  => $top_priority['id'],
                        'count'      => 10, // On avance par lot de 10 pour la stabilité [cite: 2025-11-16]
                        'status'     => 'pending',
                        'created_at' => current_time('mysql')
                    ]);

                    error_log("Zonebac Smart: Job auto créé pour la notion " . $top_priority['name']);
                }
            }
        }
    }

    public function dispatch_next_job()
    {
        global $wpdb;

        // PROTECTION : On considère qu'un job bloqué plus de 30 min est en échec
        $wpdb->query("UPDATE {$wpdb->prefix}zb_exercise_jobs SET status = 'failed' WHERE status = 'processing' AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $wpdb->query("UPDATE {$wpdb->prefix}zb_generation_jobs SET status = 'failed' WHERE status = 'processing' AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");

        // Vérification des jobs réellement actifs
        $is_processing_q = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}zb_generation_jobs WHERE status = 'processing'");
        $is_processing_ex = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}zb_exercise_jobs WHERE status = 'processing'");

        if ($is_processing_q > 0 || $is_processing_ex > 0) return;

        // Priorité aux Exercices
        $next_ex = $wpdb->get_row("SELECT id, notion_id FROM {$wpdb->prefix}zb_exercise_jobs WHERE status = 'pending' ORDER BY id ASC LIMIT 1");
        if ($next_ex) {
            wp_schedule_single_event(time(), 'zb_async_exercise_event', [$next_ex->id, $next_ex->notion_id]);
            spawn_cron();
            return;
        }

        // Sinon, Questions simples
        $next_q = $wpdb->get_row("SELECT id FROM {$wpdb->prefix}zb_generation_jobs WHERE status = 'pending' ORDER BY id ASC LIMIT 1");
        if ($next_q) {
            wp_schedule_single_event(time(), 'zb_async_generation_event', [$next_q->id]);
            spawn_cron();
        }
    }
    public function zb_execute_background_exercise($job_id, $notion_id)
    {
        if (class_exists('Zonebac_Exercise_Generator')) {
            $ex_gen = new Zonebac_Exercise_Generator();
            $ex_gen->process_exercise_generation($job_id, $notion_id);
        }
    }

    // La fonction de secours
    public function auto_process_pending_jobs()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_generation_jobs';
        $pending_jobs = $wpdb->get_results("SELECT id FROM $table WHERE status = 'pending' LIMIT 5");

        foreach ($pending_jobs as $job) {
            $wpdb->update($table, ['status' => 'processing'], ['id' => $job->id]);
            wp_schedule_single_event(time(), 'zb_async_generation_event', array($job->id));
        }
    }

    public function zb_execute_background_generation($job_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_generation_jobs';
        $notion_id = $wpdb->get_var($wpdb->prepare("SELECT notion_id FROM $table WHERE id = %d", $job_id));


        if ($notion_id) {
            // Chargement du contrôleur de génération
            if (class_exists('Zonebac_Question_Generator')) {
                $generator = new Zonebac_Question_Generator();
                $generator->process_generation_queue($job_id, $notion_id);
            }
        }
    }

    public function check_smart_schedules()
    {
        global $wpdb;
        $now = current_time('mysql');

        // Trouver les plannings qui doivent être lancés
        $due_schedules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zb_smart_schedules WHERE is_active = 1 AND next_run <= %s",
            $now
        ));

        foreach ($due_schedules as $sched) {
            $engine = new Zonebac_Smart_Engine();
            $priorities = $engine->get_priority_notions($sched->matiere_id, $sched->threshold_n);

            if (!empty($priorities)) {
                // Créer un job pour la notion la plus prioritaire
                $this->create_automatic_job($priorities[0]['id']);
            }

            // Mettre à jour la date du prochain run selon la fréquence
            $this->update_next_run($sched->id, $sched->frequency);
        }
    }

    private function create_automatic_job($notion_id)
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'zb_exercise_jobs', [
            'notion_id' => $notion_id,
            'count'     => 10, // Par défaut pour le mode intelligent
            'status'    => 'pending',
            'created_at' => current_time('mysql')
        ]);
        // On lance le dispatcher pour traiter immédiatement si possible
        $this->dispatch_next_job();
    }

    private function update_next_run($sched_id, $frequency)
    {
        global $wpdb;
        $interval = ($frequency === 'daily') ? '+1 day' : (($frequency === 'weekly') ? '+1 week' : '+1 month');
        $next_run = date('Y-m-d H:i:s', strtotime($interval));

        $wpdb->update(
            $wpdb->prefix . 'zb_smart_schedules',
            ['next_run' => $next_run, 'last_run' => current_time('mysql')],
            ['id' => $sched_id]
        );
    }
}

new ZonebacLMS();
