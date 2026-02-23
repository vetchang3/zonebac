<?php

class Zonebac_Admin_Controller
{
    public function __construct()
    {
        // LOGIQUE DE SESSION : On ne démarre que si nécessaire
        if (is_admin() && !session_id() && !headers_sent()) {
            session_start();
        }

        if (is_admin()) {
            if (isset($_GET['zb_question_id'])) {
                $_SESSION['zb_last_question_id'] = intval($_GET['zb_question_id']);
            }
        }

        add_action('admin_menu', [$this, 'add_admin_pages']);
        add_action('admin_post_zb_save_settings', [$this, 'handle_save_settings']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_early_actions']);
        add_filter('the_content', [$this, 'render_question_preview_front'], 999);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_mathjax_front']);

        // Autorise WordPress à conserver ce paramètre dans l'URL de l'admin
        add_filter('removable_query_args', function ($args) {
            return array_diff($args, array('zb_question_id'));
        });
    }

    public function enqueue_mathjax_front()
    {
        if (isset($_GET['preview_question'])) {
            wp_enqueue_script('mathjax-front', 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js', [], null, true);
            wp_add_inline_script('mathjax-front', "
                window.MathJax = {
                    tex: { inlineMath: [['$', '$'], ['\\\\(', '\\\\)']] }
                };
            ");
        }
    }

    public function render_question_preview_front($content)
    {
        if (isset($_GET['preview_question'])) {
            global $wpdb;
            $q_id = intval($_GET['preview_question']);
            $table = $wpdb->prefix . 'zb_questions';

            $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $q_id));

            if ($question) {
                $data = json_decode($question->question_data, true);

                // On vide tout contenu précédent pour être sûr de ne pas avoir de doublons
                $html = '<div class="zb-preview-container" style="border: 2px solid #0ea5e9; padding: 25px; border-radius: 12px; background: #fff; max-width: 800px; margin: 20px auto; box-shadow: 0 4px 15px rgba(0,0,0,0.1); font-family: sans-serif;">';
                $html .= '<h2 style="color: #0ea5e9; border-bottom: 2px solid #e0f2fe; padding-bottom: 10px; margin-bottom: 20px;">Aperçu de la Question Générée</h2>';

                // Énoncé avec support LaTeX
                $html .= '<div class="zb-question-text" style="font-size: 1.3em; line-height: 1.6; color: #1e293b; margin-bottom: 25px;">' . $data['question'] . '</div>';

                $html .= '<div style="display: grid; gap: 12px; margin-bottom: 30px;">';
                foreach ($data['options'] as $key => $opt) {
                    $is_correct = ($opt === $data['answer']);
                    $bg_color = $is_correct ? '#dcfce7' : '#f8fafc';
                    $border_color = $is_correct ? '#22c55e' : '#e2e8f0';
                    $text_color = $is_correct ? '#166534' : '#475569';

                    $html .= '<div style="padding: 15px; border-radius: 8px; border: 2px solid ' . $border_color . '; background: ' . $bg_color . '; color: ' . $text_color . ';">';
                    $html .= '<strong style="margin-right: 10px;">' . chr(65 + $key) . ')</strong> ' . $opt . '</div>';
                }
                $html .= '</div>';

                $html .= '<div style="background: #eff6ff; border-left: 5px solid #3b82f6; padding: 20px; border-radius: 4px;">';
                $html .= '<h4 style="margin-top: 0; color: #1e40af;">Explication Pédagogique</h4>';
                $html .= '<div style="font-style: italic; color: #1e3a8a;">' . $data['explanation'] . '</div>';
                $html .= '</div></div>';

                // IMPORTANT : On retourne le HTML et on arrête le reste du contenu
                return $html;
            }
        }
        return $content;
    }
    public function handle_early_actions()
    {
        global $wpdb;

        // Vérification stricte de la page et de l'action pour ne pas interférer ailleurs
        if (isset($_GET['page']) && $_GET['page'] === 'zonebac-questions' && isset($_GET['action']) && $_GET['action'] === 'cleanup') {
            $table_jobs = $wpdb->prefix . 'zb_generation_jobs';

            // Suppression sécurisée
            $wpdb->query("DELETE FROM $table_jobs WHERE status IN ('completed', 'failed')");

            // Redirection vers la page sans l'action pour simuler le rechargement
            wp_redirect(admin_url('admin.php?page=zonebac-questions&cleanup_done=1'));
            exit;
        }

        if (isset($_GET['page']) && $_GET['page'] === 'zonebac-bank' && isset($_GET['action'])) {
            if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
                $wpdb->delete($wpdb->prefix . 'zb_questions', ['id' => intval($_GET['id'])]);
                wp_redirect(admin_url('admin.php?page=zonebac-bank&deleted=1'));
                exit;
            }
        }
    }
    /**
     * Charge les assets CSS et JS avec un versionnage basé sur la date de modification du fichier
     */
    public function enqueue_admin_assets($hook)
    {
        // On ne charge les scripts que sur les pages du plugin pour optimiser les performances
        if (strpos($hook, 'zonebac') === false) {
            return;
        }

        // Chemins vers les répertoires
        $css_dir = plugin_dir_path(__FILE__) . '../../admin/css/';
        $js_dir  = plugin_dir_path(__FILE__) . '../../admin/js/';

        $css_url = plugin_dir_url(__FILE__) . '../../admin/css/';
        $js_url  = plugin_dir_url(__FILE__) . '../../admin/js/';

        // 1. Enqueue CSS avec filetime
        $style_file = $css_dir . 'admin-style.css';
        if (file_exists($style_file)) {
            wp_enqueue_style(
                'zonebac-admin-css',
                $css_url . 'admin-style.css',
                [],
                filemtime($style_file) // Versionnage dynamique
            );
        }

        // 2. Enqueue JS avec filetime
        $script_file = $js_dir . 'admin-generator.js';
        if (file_exists($script_file)) {
            wp_enqueue_script(
                'zonebac-admin-js',
                $js_url . 'admin-generator.js',
                ['jquery'],
                filemtime($script_file), // Versionnage dynamique
                true
            );

            // Passage des données à la vue JS
            wp_localize_script('zonebac-admin-js', 'zbData', [
                'rest_url' => get_rest_url()
            ]);
        }

        wp_enqueue_script('mathjax', 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js', [], null, true);

        // Script de configuration MathJax
        wp_add_inline_script('mathjax', "
        window.MathJax = {
            tex: { inlineMath: [['$', '$'], ['\\\\(', '\\\\)']] },
            svg: { fontCache: 'global' }
        };
    ");
    }

    public function add_admin_pages()
    {
        add_menu_page('Zonebac LMS', 'Zonebac LMS', 'manage_options', 'zonebac-lms', [$this, 'render_settings_view'], 'dashicons-welcome-learn-more', 30);
        // Sous-menu Paramétrage
        add_submenu_page('zonebac-lms', 'Paramétrage', 'Paramétrage', 'manage_options', 'zonebac-lms', [$this, 'render_settings_view']);
        // Sous-menu Banque
        add_submenu_page('zonebac-lms', 'Générateur de Questions', 'Générateur de Questions', 'manage_options', 'zonebac-questions', [$this, 'render_questions_view']);
        add_submenu_page('zonebac-lms', 'Banque de questions', 'Banque de questions', 'manage_options', 'zonebac-bank', [$this, 'render_bank_view']);
    }

    public function render_bank_view()
    {
        // Ici vous créerez une nouvelle WP_List_Table (Zonebac_Bank_List_Table)
        // qui lit la table 'zb_questions' et affiche le LaTeX.
        include_once plugin_dir_path(__FILE__) . '../../admin/views/bank-page.php';
    }
    public function handle_save_settings()
    {
        check_admin_referer('zb_settings_verify');
        $api_key = sanitize_text_field($_POST['deepseek_key']);
        Zonebac_Settings_Model::save_settings(['deepseek_key' => $api_key]);
        wp_redirect(admin_url('admin.php?page=zonebac-lms&settings_updated=true'));
        exit;
    }

    public function render_settings_view()
    {
        include_once plugin_dir_path(__FILE__) . '../../admin/views/settings-page.php';
    }

    public function render_questions_view()
    {
        global $wpdb;

        // 1. Initialisation des données pour la vue
        require_once plugin_dir_path(__FILE__) . 'class-job-list-table.php';

        $job_table = new Zonebac_Job_List_Table();
        $job_table->prepare_items();

        // 2. Gestion des messages (Flash data)
        $message = '';
        $message_type = '';
        if (isset($_GET['gen_status'])) {
            $message = ($_GET['gen_status'] === 'success') ? "Génération planifiée avec succès !" : "Erreur lors de la planification.";
            $message_type = ($_GET['gen_status'] === 'success') ? "updated" : "error";
        }


        if (isset($_GET['scheduled'])) {
            $message =
                "Le traitement a été lancé avec succès via DeepSeek.";
            $message_type = "updated";
        }

        // Message après nettoyage
        if (isset($_GET['cleanup_done'])) {
            $message = "L'historique des tâches terminées a été nettoyé.";
            $message_type = "updated";
        }

        // 3. Variables disponibles pour la vue
        $classes = get_terms(['taxonomy' => 'classe', 'hide_empty' => false]);
        $rest_url = get_rest_url();

        // 4. Inclusion de la vue
        include_once plugin_dir_path(__FILE__) . '../../admin/views/generator-page.php';
    }

    public function register_rest_routes()
    {
        register_rest_route('zonebac/v1', '/get-hierarchy', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_hierarchy_data'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function get_hierarchy_data($request)
    {
        $type = $request->get_param('type');
        $parent_id = $request->get_param('parent_id');
        $data = [];
        if ($type === 'notion') {
            $results = get_posts(['post_type' => 'notion', 'numberposts' => -1, 'tax_query' => [['taxonomy' => 'chapitre', 'field' => 'term_id', 'terms' => $parent_id]]]);
            $data = array_map(function ($p) {
                return ['id' => $p->ID, 'name' => $p->post_title];
            }, $results);
        } else {
            $taxonomy = ($type === 'matiere') ? 'matiere' : 'chapitre';
            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'meta_query' => [['key' => 'parent_id', 'value' => $parent_id]]]);
            if (!is_wp_error($terms)) {
                $data = array_map(function ($t) {
                    return ['id' => $t->term_id, 'name' => $t->name];
                }, $terms);
            }
        }
        return new WP_REST_Response($data, 200);
    }
}
