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
            params LONGTEXT NULL,
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

        $table_relations = $wpdb->prefix . 'zb_notion_relations';
        $sql_relations = "CREATE TABLE $table_relations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notion_id bigint(20) NOT NULL,
            related_notion_id bigint(20) NOT NULL,
            strength int(3) DEFAULT 1,
            PRIMARY KEY  (id),
            KEY notion_id (notion_id)
        ) $charset_collate;";

        $table_ingestion = $wpdb->prefix . 'zb_file_ingestion';
        $sql_ingestion = "CREATE TABLE $table_ingestion (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            file_name varchar(255) NOT NULL,
            file_path varchar(255) NOT NULL,
            origin_info varchar(255) NOT NULL,
            matiere_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'pending', 
            total_exercises_found int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";



        // Dans la méthode create_tables() de class-db-manager.php
        $table_sections = $wpdb->prefix . 'zb_pdf_sections';
        $sql[] = "CREATE TABLE $table_sections (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            file_id bigint(20) NOT NULL,
            section_title varchar(255) NOT NULL,
            raw_content longtext NOT NULL,
            notion_id bigint(20) DEFAULT NULL,
            status varchar(50) DEFAULT 'extracted',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY file_id (file_id)
        ) $charset_collate;";

        $table_ex = $wpdb->prefix . 'zb_exercises';
        $sql[] = "CREATE TABLE $table_ex (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notion_id bigint(20) DEFAULT NULL,
            title varchar(255) NOT NULL,
            subject_text longtext NOT NULL,
            exercise_data longtext NOT NULL,
            total_points int(11) DEFAULT 0,
            difficulty varchar(50) DEFAULT 'Moyen',
            origin_file_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        dbDelta($sql);
        dbDelta($sql_ingestion);
        dbDelta($sql_relations);
        dbDelta($sql_questions);
        dbDelta($sql_exercises);
        dbDelta($sql_jobs);
        dbDelta($sql_ex_jobs);
        dbDelta($sql_smart_schedules);
    }

    public static function migrate_tables()
    {
        global $wpdb;

        // 1. MIGRATION DE LA TABLE DES EXERCICES (zb_exercises)
        $table_ex = $wpdb->prefix . 'zb_exercises';
        $cols_ex = [
            'total_points'   => "ADD COLUMN total_points int(11) DEFAULT 0 AFTER exercise_data",
            'difficulty'     => "ADD COLUMN difficulty varchar(50) DEFAULT 'Moyen' AFTER total_points",
            'origin_file_id' => "ADD COLUMN origin_file_id bigint(20) DEFAULT NULL AFTER difficulty"
        ];

        foreach ($cols_ex as $col_name => $sql_part) {
            $exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table_ex LIKE %s", $col_name));
            if (empty($exists)) {
                $wpdb->query("ALTER TABLE $table_ex $sql_part");
                error_log("ZB DEBUG: Table Exercises - Colonne $col_name ajoutée.");
            }
        }

        // 2. MIGRATION DE LA TABLE D'INGESTION (zb_file_ingestion)
        $table_ingest = $wpdb->prefix . 'zb_file_ingestion';
        $cols_ingest = [
            'classe_id' => "ADD COLUMN classe_id bigint(20) DEFAULT NULL AFTER matiere_id"
        ];

        foreach ($cols_ingest as $col_name => $sql_part) {
            $exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table_ingest LIKE %s", $col_name));
            if (empty($exists)) {
                $wpdb->query("ALTER TABLE $table_ingest $sql_part");
                error_log("ZB DEBUG: Table Ingestion - Colonne $col_name ajoutée.");
            }
        }
    }
}
