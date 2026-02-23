<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Zonebac_Job_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'job',
            'plural'   => 'jobs',
            'ajax'     => false
        ]);
    }

    // 1. Définition des colonnes avec support du tri
    public function get_columns()
    {
        return array(
            'cb'      => '<input type="checkbox" />',
            'title'   => 'Récapitulatif de Génération',
            'details' => 'Hiérarchie',
            'count'   => 'Qté',
            'status'  => 'Statut',
            'date'    => 'Date'
        );
    }

    // 4. Ajout du tri (Sortable columns)
    protected function get_sortable_columns()
    {
        return array(
            'count' => array('count', false),
            'date'  => array('created_at', true),
            'status' => array('status', false)
        );
    }

    // 2. Actions groupées (Bulk Actions)
    protected function get_bulk_actions()
    {
        return array(
            'bulk-delete' => 'Supprimer définitivement',
            'bulk-run'    => 'Lancer les tâches sélectionnées'
        );
    }

    // 5. Actions au survol (Row Actions) sur la colonne titre
    public function column_title($item)
    {
        // Point 4 : Le titre reste la Notion
        $title = $item['notion'];

        // Point 6 : On retire le lien "Voir", on garde "Supprimer"
        $actions = [
            'delete' => sprintf('<a href="?page=%s&action=delete&job=%d" onclick="return confirm(\'Supprimer ?\')">Supprimer</a>', $_REQUEST['page'], $item['id']),
        ];

        if (strtoupper($item['status']) === 'PROCESSING') {
            $actions['run'] = '<span style="color:#999;">Génération en cours...</span>';
        } else {
            $actions['run'] = sprintf('<a href="?page=%s&action=run&job=%d">Relancer</a>', $_REQUEST['page'], $item['id']);
        }

        return sprintf('<strong>%s</strong> %s', esc_html($title), $this->row_actions($actions));
    }

    // Point 5 : Ajouter le bouton Nettoyer au-dessus du tableau
    public function extra_tablenav($which)
    {
        if ($which == "top") {
            echo '<div class="alignleft actions">';
            echo sprintf('<a href="?page=%s&action=cleanup" class="button secondary" onclick="return confirm(\'Supprimer tous les jobs terminés ?\')">Nettoyer les jobs terminés</a>', $_REQUEST['page']);
            echo '</div>';
        }
    }

    /**
     * Rendu stylisé du statut avec couleurs
     */
    public function column_status($item)
    {
        $status = strtoupper($item['status']);

        // Définition des styles professionnels
        $styles = [
            'PENDING'    => 'background-color: #fef3c7; color: #92400e; border: 1px solid #f59e0b;',
            'PROCESSING' => 'background-color: #e0f2fe; color: #075985; border: 1px solid #0ea5e9;',
            'COMPLETED'  => 'background-color: #dcfce7; color: #166534; border: 1px solid #22c55e;',
            'FAILED'     => 'background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444;',
        ];

        $current_style = isset($styles[$status]) ? $styles[$status] : 'background-color: #f3f4f6; color: #374151;';
        $loader = '';

        // Si c'est en cours, on ajoute le spinner de WordPress
        if ($status === 'PROCESSING') {
            $loader = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0; vertical-align:middle;"></span>';
        }

        return sprintf(
            '<span style="padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 11px; display: inline-block; %s">%s %s</span>',
            $current_style,
            $loader,
            $status
        );
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="job[]" value="%d" />', $item['id']);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'status':
                $val = strtoupper($item['status']);
                $class = ($val === 'COMPLETED') ? 'status-completed' : 'status-pending';
                return sprintf('<span class="status-badge %s">%s</span>', $class, $val);
            default:
                return isset($item[$column_name]) ? $item[$column_name] : '-';
        }
    }

    public function prepare_items()
    {
        global $wpdb;
        $table_jobs = $wpdb->prefix . 'zb_generation_jobs';

        $this->process_action_logic();

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());

        // 1. Sécurisation du tri (Liste blanche)
        $orderby_column = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'created_at';
        $order_direction = (!empty($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

        // 2. Validation de la colonne (Éviter les injections SQL)
        $valid_columns = ['count', 'created_at', 'status'];
        if (!in_array($orderby_column, $valid_columns)) {
            $orderby_column = 'created_at';
        }

        // 3. Construction de la requête sans guillemets sur DESC/ASC
        // Note : on utilise %i pour l'identifiant (colonne) mais pas pour le mot-clé ORDER
        $query = "SELECT * FROM $table_jobs ORDER BY `$orderby_column` $order_direction";
        $results = $wpdb->get_results($query, ARRAY_A);

        if ($results) {
            foreach ($results as $key => $item) {
                $post = get_post($item['notion_id']);
                $results[$key]['notion'] = $post ? $post->post_title : 'ID: ' . $item['notion_id'];

                $chap = wp_get_post_terms($item['notion_id'], 'chapitre')[0]->name ?? '-';
                $mat  = wp_get_post_terms($item['notion_id'], 'matiere')[0]->name ?? '-';
                $cla  = wp_get_post_terms($item['notion_id'], 'classe')[0]->name ?? '-';
                $results[$key]['details'] = sprintf('<span class="zb-path">%s &gt; %s &gt; %s</span>', $cla, $mat, $chap);
                $results[$key]['date'] = $item['created_at'];
            }
        }

        $this->items = $results ? $results : array();
    }

    private function process_action_logic()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_generation_jobs';
        $action = $this->current_action();



        // Logique de nettoyage
        if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
            $wpdb->query("DELETE FROM $table WHERE status IN ('completed', 'failed')");

            // REDIRECTION : On retire l'argument 'action' pour revenir à la liste propre
            $redirect_url = remove_query_arg('action', wp_get_referer());
            if (!$redirect_url) {
                $redirect_url = admin_url('admin.php?page=zonebac-questions');
            }

            wp_redirect($redirect_url . '&cleanup_done=1');
            exit; // Indispensable pour éviter la page blanche
        }

        // Suppression individuelle
        if ($action === 'delete' && isset($_GET['job'])) {
            $wpdb->delete($table, ['id' => intval($_GET['job'])]);
        }

        if ($action === 'run' && isset($_GET['job'])) {
            $job_id = intval($_GET['job']);

            // 1. Mise à jour immédiate du statut
            $wpdb->update($table, ['status' => 'processing'], ['id' => $job_id]);

            // 2. Planification asynchrone (WP_Cron)
            wp_schedule_single_event(time() + 1, 'zb_async_generation_event', [$job_id]);

            // 3. Notification de succès (S'affichera au rafraîchissement)
            add_settings_error(
                'zb_messages',
                'zb_run_scheduled',
                'La génération a été lancée en arrière-plan. Elle sera complétée d\'ici quelques instants.',
                'updated'
            );

        }
    }
}
