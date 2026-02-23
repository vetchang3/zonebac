<?php

class Zonebac_DB_Manager
{
    public static function create_tables()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'zb_questions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notion_id bigint(20) NOT NULL,
            difficulty varchar(20) NOT NULL,
            points int(11) NOT NULL,
            question_data json NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);


        $table_jobs = $wpdb->prefix . 'zb_generation_jobs';
        $sql_jobs = "CREATE TABLE $table_jobs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notion_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'pending', -- pending, processing, completed, failed
            count int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_jobs);

    }
}
