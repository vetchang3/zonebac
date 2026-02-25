<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

abstract class Zonebac_Base_Job_Table extends WP_List_Table
{

    // Code commun pour le rendu stylisé des statuts
    public function column_status($item)
    {
        $status = strtoupper($item['status']);
        $styles = [
            'PENDING'    => 'background-color: #fef3c7; color: #92400e; border: 1px solid #f59e0b;',
            'PROCESSING' => 'background-color: #e0f2fe; color: #075985; border: 1px solid #0ea5e9;',
            'COMPLETED'  => 'background-color: #dcfce7; color: #166534; border: 1px solid #22c55e;',
            'FAILED'     => 'background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444;',
        ];

        $current_style = isset($styles[$status]) ? $styles[$status] : 'background-color: #f3f4f6; color: #374151;';
        $loader = ($status === 'PROCESSING') ? '<span class="spinner is-active" style="float:none; margin:0 5px 0 0; vertical-align:middle;"></span>' : '';

        return sprintf(
            '<span style="padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 11px; display: inline-block; %s">%s %s</span>',
            $current_style,
            $loader,
            $status
        );
    }

    // Bulk actions communes
    protected function get_bulk_actions()
    {
        return [
            'bulk-delete' => 'Supprimer définitivement',
            'bulk-run'    => 'Lancer les tâches sélectionnées'
        ];
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="job[]" value="%d" />', $item['id']);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'count':
                return $item['count'];
            case 'date':
                return date('d/m/Y H:i', strtotime($item['created_at']));
            default:
                return '-';
        }
    }
    /**
     * Insère du contenu personnalisé (notre bouton nettoyer) dans la barre de navigation native
     */
    protected function extra_tablenav($which)
    {
        if ($which === 'top') {
            // On crée une action spécifique selon la page (ex: cleanup_questions ou cleanup_exercises)
            $action_name = ($_REQUEST['page'] === 'zonebac-questions') ? 'cleanup_questions' : 'cleanup_exercises';
            $cleanup_url = add_query_arg('action', $action_name, menu_page_url($_REQUEST['page'], false));
?>
            <div class="alignleft actions">
                <a href="<?php echo esc_url($cleanup_url); ?>"
                    class="button secondary"
                    onclick="return confirm('Voulez-vous vraiment nettoyer l\'historique ?')">
                    Nettoyer les jobs terminés
                </a>
            </div>
<?php
        }
    }
}
