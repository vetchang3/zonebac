<?php

if (!defined('ABSPATH')) exit;

class Zonebac_DB_Manager
{
    /**
     * Crée ou met à jour l'ensemble des tables du plugin Zonebac
     */
    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. Table des Questions (Centralisation du LaTeX et données JSON)
        $table_questions = $wpdb->prefix . 'zb_questions';
        $sql_questions = "CREATE TABLE $table_questions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notion_id bigint(20) NOT NULL,
            difficulty varchar(20) NOT NULL,
            points int(11) NOT NULL DEFAULT 1,
            question_data longtext NOT NULL,
            status varchar(20) DEFAULT 'published',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 2. Table des Exercices (Stockage des 10 questions générées)
        $table_exercises = $wpdb->prefix . 'zb_exercises';
        $sql_exercises = "CREATE TABLE $table_exercises (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notion_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            subject_text longtext NOT NULL,
            exercise_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 3. File d'attente : Jobs de Questions
        $table_jobs = $wpdb->prefix . 'zb_generation_jobs';
        $sql_jobs = "CREATE TABLE $table_jobs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notion_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            count int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 4. File d'attente : Jobs d'Exercices
        $table_ex_jobs = $wpdb->prefix . 'zb_exercise_jobs';
        $sql_ex_jobs = "CREATE TABLE $table_ex_jobs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notion_id bigint(20) NOT NULL,
            count int(11) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 5. NOUVEAUTÉ : Plannings de Génération Intelligente (Le Cerveau)
        $table_smart_schedules = $wpdb->prefix . 'zb_smart_schedules';
        $sql_smart_schedules = "CREATE TABLE $table_smart_schedules (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            matiere_id bigint(20) NOT NULL,
            threshold_n int(11) DEFAULT 50,
            frequency varchar(20) DEFAULT 'weekly',
            next_run datetime DEFAULT NULL,
            last_run datetime DEFAULT NULL,
            last_error text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Exécution sécurisée via dbDelta
        dbDelta($sql_questions);
        dbDelta($sql_exercises);
        dbDelta($sql_jobs);
        dbDelta($sql_ex_jobs);
        dbDelta($sql_smart_schedules);
    }
}
