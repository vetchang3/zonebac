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
}
