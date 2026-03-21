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
            'cb'         => '<input type="checkbox" />',
            'title'      => 'Titre de l\'Exercice',
            'difficulty' => 'Difficulté', // AJOUTÉ
            'total_points' => 'Points',   // AJOUTÉ
            'classe'     => 'Classe',
            'matiere'    => 'Matière',
            'notion'     => 'Notion',
            'source'     => 'Source / Origine',
            'q_count'    => 'Questions',
            'date'       => 'Date'
        ];
    }

    public function column_source($item)
    {
        // On décode les données de l'exercice pour trouver l'origine [cite: 2026-02-23]
        $data = json_decode($item['exercise_data'], true);

        // On vérifie l'origine dans les métadonnées ou le job
        $origin = $data['metadata']['origin'] ?? 'IA Zonebac';
        $file_ref = $data['metadata']['file_reference'] ?? '';

        // Détection de la source
        $is_archive = ($item['notion_id'] == 0 || (strpos($origin, 'IA') === false));

        if ($is_archive) {
            return sprintf(
                '<div style="display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-media-document" style="color:#8b5cf6;" title="Archive PDF"></span>
                <div>
                    <span class="badge-archive" style="background:#ede9fe; color:#5b21b6; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">ARCHIVE</span><br>
                    <small style="color:#64748b;">%s</small>
                </div>
            </div>',
                esc_html($origin)
            );
        } else {
            return sprintf(
                '<div style="display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-admin-appearance" style="color:#10b981;" title="Généré par IA"></span>
                <div>
                    <span class="badge-ia" style="background:#dcfce7; color:#166534; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">IA GENERATED</span><br>
                    <small style="color:#64748b;">Dispatcher Automatique</small>
                </div>
            </div>'
            );
        }
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="exercise[]" value="%d" />', $item['id']);
    }

    public function column_title($item)
    {
        // Bouton Modifier : On pointe vers la page d'édition dédiée
        $edit_url = admin_url('admin.php?page=zonebac-ex-edit&id=' . $item['id']);

        $actions = [
            'view'   => sprintf('<a href="#" class="zb-view-exercise" data-id="%d">Voir</a>', $item['id']),
            'edit'   => sprintf('<a href="%s">Modifier</a>', $edit_url),
            'delete' => sprintf('<a href="?page=%s&action=delete&id=%d" onclick="return confirm(\'Supprimer définitivement cet exercice ?\')">Supprimer</a>', $_REQUEST['page'], $item['id']),
        ];

        return sprintf('<strong>%1$s</strong> %2$s', esc_html($item['title']), $this->row_actions($actions));
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
                return ($notion_id > 0) ? $this->get_term_name($notion_id, 'classe') : '<span class="description">Extraite du PDF</span>';
            case 'matiere':
                return ($notion_id > 0) ? $this->get_term_name($notion_id, 'matiere') : '<span class="description">Extraite du PDF</span>';
            case 'notion':
                return ($notion_id > 0) ? get_the_title($notion_id) : '<em>Non classé (Ingestion)</em>';
            case 'date':
                return date('d/m/Y H:i', strtotime($item['created_at']));
            case 'source':
                return $this->column_source($item); // Appel explicite
            case 'difficulty':
                $colors = ['Facile' => '#22c55e', 'Moyen' => '#f59e0b', 'Difficile' => '#ef4444'];
                $color = $colors[$item['difficulty']] ?? '#64748b';
                return sprintf('<span style="color:%s; font-weight:bold;">%s</span>', $color, $item['difficulty']);
            case 'total_points':
                return '<strong>' . $item['total_points'] . ' pts</strong>';
            default:
                return '-';
        }
    }

    public function prepare_items()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_exercises';

        // --- DÉBUT DU BLOC À AJOUTER : GESTION BULK DELETE ---
        if ($this->current_action() === 'bulk-delete') {
            // 'exercise' correspond au nom (name) donné dans column_cb()
            $ids = isset($_REQUEST['exercise']) ? array_map('intval', $_REQUEST['exercise']) : [];
            if (!empty($ids)) {
                $wpdb->query("DELETE FROM $table WHERE id IN (" . implode(',', $ids) . ")");
                echo '<div class="updated"><p>' . count($ids) . ' exercices supprimés avec succès.</p></div>';
            }
        }
        // --- FIN DU BLOC À AJOUTER ---

        // Suppression individuelle (déjà présente dans ton code)
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $wpdb->delete($table, ['id' => intval($_GET['id'])]);
        }

        $per_page = 20;
        $current_page = $this->get_pagenum();

        // On compte TOUT
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $offset = ($current_page - 1) * $per_page;

        // On récupère TOUT par date décroissante
        $this->items = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT $offset, $per_page",
            ARRAY_A
        );

        $this->_column_headers = [$this->get_columns(), [], []];
    }

    public function get_bulk_actions()
    {
        return ['bulk-delete' => 'Supprimer définitivement'];
    }
}
