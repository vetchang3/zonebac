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
        }

        add_action('zb_async_generation_event', [$this, 'zb_execute_background_generation']);

        // On écoute la fin d'un traitement pour tenter de lancer le suivant
        add_action('zb_job_completed', [$this, 'dispatch_next_job']);

        // On garde votre cron de secours mais on le pointe vers dispatch_next_job
        if (!wp_next_scheduled('zb_check_pending_jobs_cron')) {
            wp_schedule_event(time(), 'hourly', 'zb_check_pending_jobs_cron');
        }
        add_action('zb_check_pending_jobs_cron', [$this, 'dispatch_next_job']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_scripts']);

        add_filter('query_vars', function ($vars) {
            $vars[] = 'zb_question_id';
            return $vars;
        });
    }

    /**
     * Le "Dispatcher" : Vérifie si on peut lancer un nouveau job
     */
    public function dispatch_next_job()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_generation_jobs';

        // 1. On vérifie si un job est DÉJÀ en cours de traitement
        $is_processing = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'processing'");

        if ($is_processing > 0) {
            // Un job tourne déjà, on ne fait rien pour respecter la file d'attente
            return;
        }

        // 2. On récupère le prochain job en attente (le plus ancien)
        $next_job = $wpdb->get_row("SELECT id FROM $table WHERE status = 'pending' ORDER BY id ASC LIMIT 1");

        if ($next_job) {
            // On le lance immédiatement
            $wpdb->update($table, ['status' => 'processing'], ['id' => $next_job->id]);
            wp_schedule_single_event(time(), 'zb_async_generation_event', array($next_job->id));
            spawn_cron();
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
}

new ZonebacLMS();
