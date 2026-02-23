<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Zonebac_Bank_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'question',
            'plural'   => 'questions',
            'ajax'     => false
        ]);
    }

    public function get_columns()
    {
        return [
            'cb'         => '<input type="checkbox" />',
            'title'      => 'Énoncé',
            'classe'     => 'Classe',
            'matiere'    => 'Matière',
            'chapitre'   => 'Chapitre',
            'notion'     => 'Notion',
            'image'      => 'Image'
        ];
    }

    protected function get_sortable_columns()
    {
        return [
            'title' => ['title', false],
            'classe' => ['classe', false],
            'matiere' => ['matiere', false]
        ];
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="question[]" value="%d" />', $item['id']);
    }

    public function column_title($item)
    {
        $data = json_decode($item['question_data'], true);
        $excerpt = wp_trim_words($data['question'], 10, '...');

        // On passe l'ID de la question en paramètre pour que l'éditeur sache quoi charger
        $edit_link = admin_url("post.php?post={$item['notion_id']}&action=edit&zb_question_id={$item['id']}");
        $view_link = get_permalink($item['notion_id']) . "?preview_question=" . $item['id'];

        $actions = [
            'view'   => sprintf('<a href="%s" target="_blank">Voir</a>', $view_link),
            'edit'   => sprintf('<a href="%s">Modifier</a>', $edit_link),
            'delete' => sprintf('<a href="?page=%s&action=delete&id=%d" onclick="return confirm(\'Supprimer ?\')">Supprimer</a>', $_REQUEST['page'], $item['id']),
        ];

        return sprintf('<strong>%s</strong> %s', esc_html($excerpt), $this->row_actions($actions));
    }

    public function column_image($item)
    {
        $data = json_decode($item['question_data'], true);
        if (!empty($data['image_suggestion'])) {
            return '<span class="dashicons dashicons-format-image" title="' . esc_attr($data['image_suggestion']) . '"></span>';
        }
        return '-';
    }

    // Méthodes pour afficher la hiérarchie (Classe, Matière, etc.)
    public function column_default($item, $column_name)
    {
        $notion_id = $item['notion_id'];
        switch ($column_name) {
            case 'classe':
                return wp_get_post_terms($notion_id, 'classe')[0]->name ?? '-';
            case 'matiere':
                return wp_get_post_terms($notion_id, 'matiere')[0]->name ?? '-';
            case 'chapitre':
                return wp_get_post_terms($notion_id, 'chapitre')[0]->name ?? '-';
            case 'notion':
                return get_the_title($notion_id);
            default:
                return '-';
        }
    }

    public function prepare_items()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_questions';

        // 1. Gestion de la suppression individuelle
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $wpdb->delete($table, ['id' => intval($_GET['id'])]);
            // Le rafraîchissement se fera via la redirection dans le contrôleur principal
        }

        // 2. Configuration du Tri
        $orderby = (!empty($_GET['orderby'])) ? esc_sql($_GET['orderby']) : 'created_at';
        $order   = (!empty($_GET['order'])) ? esc_sql($_GET['order']) : 'DESC';

        // Tri spécial pour l'énoncé stocké en JSON
        if ($orderby === 'title') {
            $orderby = "JSON_EXTRACT(question_data, '$.question')";
        }

        // 3. Pagination
        $per_page = 15;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $offset = ($current_page - 1) * $per_page;
        $this->items = $wpdb->get_results("SELECT * FROM $table ORDER BY $orderby $order LIMIT $offset, $per_page", ARRAY_A);
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }
}
