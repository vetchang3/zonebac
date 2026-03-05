<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

require_once 'class-base-job-table.php';
class Zonebac_Exercise_Job_Table extends Zonebac_Base_Job_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'job_ex',
            'plural'   => 'jobs_ex',
            'ajax'     => false
        ]);
    }

    public function get_columns()
    {
        return [
            'cb'      => '<input type="checkbox" />',
            'notion'  => 'Notion / Sujet',
            'count'   => 'Questions',
            'status'  => 'Statut',
            'date'    => 'Date'
        ];
    }

    public function column_notion_id($item)
    {
        if ($item['notion_id'] == 0) {
            // C'est un exercice extrait d'un PDF
            return '<span class="dashicons dashicons-media-document" style="color:#8b5cf6;" title="Extrait d\'un PDF"></span> <strong>Archive</strong>';
        } else {
            // C'est une génération automatique par Gap
            return '<span class="dashicons dashicons-smart-machine" style="color:#10b981;" title="Génération IA"></span> ' . get_the_title($item['notion_id']);
        }
    }
    public function column_notion($item)
    {
        $icon = '';
        $label = '';

        if (intval($item['notion_id']) === 0) {
            // On récupère le nom du fichier dans les params
            $params = json_decode($item['params'], true);
            $file_name = !empty($params['file_reference']) ? $params['file_reference'] : 'Archive PDF';

            $icon = '<span class="dashicons dashicons-media-document" style="color:#8b5cf6; margin-right:8px;"></span>';
            $label = '<span style="color:#8b5cf6; font-weight:bold;">' . esc_html($file_name) . '</span>';
        } else {
            $icon = '<span class="dashicons dashicons-smart-machine" style="color:#10b981; margin-right:8px;"></span>';
            $label = get_the_title($item['notion_id']);
        }

        // Conservation des actions
        $actions = [
            'delete' => sprintf('<a href="?page=%s&action=delete_job&job=%d" onclick="return confirm(\'Supprimer ?\')">Supprimer</a>', $_REQUEST['page'], $item['id']),
        ];
        $actions['run'] = (strtoupper($item['status']) === 'PROCESSING')
            ? '<span style="color:#991b1b;">Génération...</span>'
            : sprintf('<a href="?page=%s&action=run_job&job=%d">Relancer</a>', $_REQUEST['page'], $item['id']);

        return sprintf('<div style="display:flex; align-items:center;">%s %s</div> %s', $icon, $label, $this->row_actions($actions));
    }

    public function prepare_items()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_exercise_jobs';

        $this->handle_table_actions(); // Nouvelle méthode à créer ci-dessous

        $this->_column_headers = [$this->get_columns(), [], []];
        $this->items = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);
    }

    private function handle_table_actions()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_exercise_jobs';
        $action = $this->current_action();

        // Action : Nettoyer
        if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
            $wpdb->query("DELETE FROM $table WHERE status IN ('completed', 'failed')");
            wp_redirect(remove_query_arg('action', wp_get_referer()));
            exit;
        }

        // Action : Relancer individuel
        if ($action === 'run_job' && isset($_GET['job'])) {
            $job_id = intval($_GET['job']);
            $wpdb->update($table, ['status' => 'pending'], ['id' => $job_id]);

            $lms = new ZonebacLMS();
            $lms->dispatch_next_job();
        }

        // Action : Supprimer individuel
        if ($action === 'delete_job' && isset($_GET['job'])) {
            $wpdb->delete($table, ['id' => intval($_GET['job'])]);
        }
    }
}
