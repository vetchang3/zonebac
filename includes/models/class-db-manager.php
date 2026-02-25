<?php

class Zonebac_DB_Manager
{
    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. Table des questions (Existante)
        $table_questions = $wpdb->prefix . 'zb_questions';
        $sql_questions = "CREATE TABLE $table_questions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        notion_id bigint(20) NOT NULL,
        difficulty varchar(20) NOT NULL,
        points int(11) NOT NULL,
        question_data json NOT NULL,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
        dbDelta($sql_questions);

        // 2. Table des jobs de questions (Existante)
        $table_jobs = $wpdb->prefix . 'zb_generation_jobs';
        $sql_jobs = "CREATE TABLE $table_jobs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        notion_id bigint(20) NOT NULL,
        status varchar(20) DEFAULT 'pending',
        count int(11) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
        dbDelta($sql_jobs);

        // 3. Table des exercices (Nouvelle)
        $table_exercises = $wpdb->prefix . 'zb_exercises';
        $sql_exercises = "CREATE TABLE $table_exercises (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        notion_id bigint(20) NOT NULL,
        title varchar(255) NOT NULL,
        subject_text text NOT NULL,
        exercise_data longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
        dbDelta($sql_exercises); // Ajout du dbDelta manquant

        // 4. Table des jobs d'exercices (Nouvelle)
        $table_ex_jobs = $wpdb->prefix . 'zb_exercise_jobs';
        $sql_ex_jobs = "CREATE TABLE $table_ex_jobs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        notion_id bigint(20) NOT NULL,
        count int(11) NOT NULL,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
        dbDelta($sql_ex_jobs); // Ajout du dbDelta manquant
    }
}
