<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Zonebac_Exercise_Bank_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'exercice',
            'plural'   => 'exercices',
            'ajax'     => false
        ]);
    }

    public function get_columns()
    {
        return [
            'cb'       => '<input type="checkbox" />',
            'title'    => 'Titre de l\'Exercice',
            'classe'   => 'Classe',
            'matiere'  => 'Matière',
            'notion'   => 'Notion',
            'q_count'  => 'Questions',
            'date'     => 'Date'
        ];
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="exercise[]" value="%d" />', $item['id']);
    }

    public function column_title($item)
    {
        // On génère les liens pour l'aperçu, l'édition et la suppression
        $preview_link = get_permalink($item['notion_id']) . "?preview_exercise=" . $item['id'];
        $edit_link    = admin_url("admin.php?page=zonebac-ex-edit&id={$item['id']}");
        $delete_url   = sprintf('?page=%s&action=delete&id=%d', $_REQUEST['page'], $item['id']);

        $actions = [
            'view'   => sprintf('<a href="%s" target="_blank">Voir l\'exercice</a>', $preview_link),
            'edit'   => sprintf('<a href="%s">Modifier</a>', $edit_link), // Nouvelle ligne ajoutée
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'Supprimer ?\')">Supprimer</a>', $delete_url),
        ];

        return sprintf('<strong>%s</strong> %s', esc_html($item['title']), $this->row_actions($actions));
    }

    public function column_q_count($item)
    {
        $data = json_decode($item['exercise_data'], true);
        $count = is_array($data) ? count($data) : 0;
        return sprintf('<span class="badge" style="background:#0ea5e9; color:#fff; padding:2px 8px; border-radius:10px;">%d Qs</span>', $count);
    }

    public function column_default($item, $column_name)
    {
        $notion_id = $item['notion_id'];
        switch ($column_name) {
            case 'classe':
                return wp_get_post_terms($notion_id, 'classe')[0]->name ?? '-';
            case 'matiere':
                return wp_get_post_terms($notion_id, 'matiere')[0]->name ?? '-';
            case 'notion':
                return get_the_title($notion_id);
            case 'date':
                return date('d/m/Y H:i', strtotime($item['created_at']));
            default:
                return '-';
        }
    }

    public function prepare_items()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_exercises';

        // Gestion de la suppression
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $wpdb->delete($table, ['id' => intval($_GET['id'])]);
        }

        $per_page = 10;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $offset = ($current_page - 1) * $per_page;
        $this->items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A);

        $this->_column_headers = [$this->get_columns(), [], []];
    }
}
