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

        add_action('admin_post_zb_save_exercise_edit', [$this, 'handle_save_exercise_edit']);
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
        global $wpdb;

        // --- CAS 1 : PREVIEW D'UNE QUESTION UNIQUE ---
        if (isset($_GET['preview_question'])) {
            $q_id = intval($_GET['preview_question']);
            $table = $wpdb->prefix . 'zb_questions';
            $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $q_id));

            if ($question) {
                $data = json_decode($question->question_data, true);

                ob_start();
                echo '<div class="zb-preview-container" style="background:#f9f9f9; padding:20px; border-radius:10px; border:1px solid #ddd; margin:20px 0;">';
                echo '<span style="background:#0073aa; color:#fff; padding:3px 8px; border-radius:5px; font-size:12px;">Prévisualisation Question #' . $q_id . '</span>';
                echo '<h2 style="margin-top:15px;">' . wpautop($data['question']) . '</h2>';

                echo '<ul style="list-style:none; padding-left:0;">';
                foreach ($data['options'] as $index => $option) {
                    $is_correct = ($option === $data['answer']);
                    $style = $is_correct ? 'border:2px solid #46b450; background:#edfaef;' : 'border:1px solid #ccc;';
                    echo '<li style="padding:10px; margin-bottom:10px; border-radius:5px; ' . $style . '">';
                    echo '<strong>' . chr(65 + $index) . '.</strong> ' . esc_html($option);
                    if ($is_correct) echo ' ✅ (Bonne réponse)';
                    echo '</li>';
                }
                echo '</ul>';

                if (!empty($data['explanation'])) {
                    echo '<div style="background:#e1f0fa; padding:15px; border-left:4px solid #0073aa; margin-top:20px;">';
                    echo '<strong>Explication :</strong><br>' . wpautop($data['explanation']);
                    echo '</div>';
                }
                echo '</div>';

                return ob_get_clean();
            }
        }

        // --- CAS 2 : PREVIEW D'UN EXERCICE COMPLET ---
        if (isset($_GET['preview_exercise'])) {
            $ex_id = intval($_GET['preview_exercise']);
            $table = $wpdb->prefix . 'zb_exercises';
            $exercise = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $ex_id));

            if ($exercise) {
                $questions = json_decode($exercise->exercise_data, true);

                ob_start();
                echo '<div class="zb-exercise-preview" style="max-width:800px; margin:auto;">';
                echo '<h1>' . esc_html($exercise->title) . '</h1>';
                echo '<div class="zb-subject" style="background:#fff; padding:20px; border:1px solid #eee; border-radius:8px; margin-bottom:30px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">';
                echo wpautop($exercise->subject_text);
                echo '</div>';

                foreach ($questions as $i => $q) {
                    echo '<div class="zb-q-item" style="margin-bottom:40px; padding:20px; background:#fdfdfd; border-left:5px solid #0073aa; box-shadow:0 2px 4px rgba(0,0,0,0.05);">';
                    echo '<h3>Question ' . ($i + 1) . ' <span style="font-size:0.7em; color:#666;">(' . esc_html($q['type'] ?? 'single') . ')</span></h3>';
                    echo '<p>' . wpautop($q['question']) . '</p>';

                    echo '<div class="zb-options-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">';
                    foreach ($q['options'] as $idx => $opt) {
                        // Logique de vérification de la réponse (string ou array)
                        $is_correct = false;
                        if (is_array($q['answer'])) {
                            $is_correct = in_array($opt, $q['answer']);
                        } else {
                            $is_correct = ($opt === $q['answer']);
                        }

                        $bg = $is_correct ? '#dcfce7' : '#fff';
                        $border = $is_correct ? '#22c55e' : '#ddd';

                        echo '<div style="padding:10px; border:1px solid ' . $border . '; background:' . $bg . '; border-radius:5px;">';
                        echo '<strong>' . chr(65 + $idx) . '.</strong> ' . esc_html($opt);
                        echo '</div>';
                    }
                    echo '</div>';

                    echo '<div style="margin-top:15px; font-size:0.9em; color:#555; font-style:italic;">';
                    echo '<strong>Correction :</strong> ' . wpautop($q['explanation']);
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';

                return ob_get_clean();
            }
        }

        return $content;
    }

    public function handle_early_actions()
    {
        global $wpdb;

        // --- 1. NETTOYAGE DES JOBS DE QUESTIONS ---
        if (isset($_GET['action']) && $_GET['action'] === 'cleanup_questions') {
            $table_jobs = $wpdb->prefix . 'zb_generation_jobs';
            $wpdb->query("DELETE FROM $table_jobs WHERE status IN ('completed', 'failed')");

            wp_redirect(admin_url('admin.php?page=zonebac-questions&cleanup_done=1'));
            exit;
        }

        // --- 2. NETTOYAGE DES JOBS D'EXERCICES ---
        if (isset($_GET['action']) && $_GET['action'] === 'cleanup_exercises') {
            $table_jobs = $wpdb->prefix . 'zb_exercise_jobs';
            $wpdb->query("DELETE FROM $table_jobs WHERE status IN ('completed', 'failed')");

            wp_redirect(admin_url('admin.php?page=zonebac-ex-gen&cleanup_done=1'));
            exit;
        }

        // --- 3. SUPPRESSION D'UNE QUESTION DANS LA BANQUE ---
        if (isset($_GET['page']) && $_GET['page'] === 'zonebac-bank' && isset($_GET['action'])) {
            if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
                $wpdb->delete($wpdb->prefix . 'zb_questions', ['id' => intval($_GET['id'])]);
                wp_redirect(admin_url('admin.php?page=zonebac-bank&deleted=1'));
                exit;
            }
        }

        // --- 4. SUPPRESSION D'UN EXERCICE DANS LA BANQUE ---
        if (isset($_GET['page']) && $_GET['page'] === 'zonebac-ex-bank' && isset($_GET['action'])) {
            if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
                $wpdb->delete($wpdb->prefix . 'zb_exercises', ['id' => intval($_GET['id'])]);
                wp_redirect(admin_url('admin.php?page=zonebac-ex-bank&deleted=1'));
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

        add_submenu_page('zonebac-lms', 'Générateur d\'Exercices', 'Générateur d\'Exercices', 'manage_options', 'zonebac-ex-gen', [$this, 'render_ex_generator_view']);
        add_submenu_page('zonebac-lms', 'Banque d\'Exercices', 'Banque d\'Exercices', 'manage_options', 'zonebac-ex-bank', [$this, 'render_ex_bank_view']);
        add_submenu_page(null, 'Éditer l\'Exercice', 'Éditer l\'Exercice', 'manage_options', 'zonebac-ex-edit', [$this, 'render_ex_edit_view']);
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

    public function render_ex_generator_view()
    {
        global $wpdb;
        $classes = get_terms(['taxonomy' => 'classe', 'hide_empty' => false]);

        // LOGIQUE DE NOTIFICATION IDENTIQUE AUX QUESTIONS
        $message = '';
        $message_type = '';

        if (isset($_GET['success'])) {
            $message = "La génération de l'exercice a été lancée avec succès via DeepSeek.";
            $message_type = "updated"; // Vert
        }

        if (isset($_GET['cleanup_done'])) {
            $message = "L'historique des exercices terminés a été nettoyé.";
            $message_type = "updated";
        }

        // Initialisation de la table
        require_once plugin_dir_path(__FILE__) . 'class-exercise-job-table.php';
        $ex_job_table = new Zonebac_Exercise_Job_Table();
        $ex_job_table->prepare_items();

        include_once plugin_dir_path(__FILE__) . '../../admin/views/exercise-generator-page.php';
    }

    public function render_ex_bank_view()
    {
        // Chargement de la table
        require_once plugin_dir_path(__FILE__) . 'class-exercise-bank-table.php';
        $ex_bank_table = new Zonebac_Exercise_Bank_Table();

        // Inclusion de la vue que nous avons créée précédemment
        include_once plugin_dir_path(__FILE__) . '../../admin/views/exercise-bank-page.php';
    }

    /**
     * Affiche la page d'édition de l'exercice
     */
    public function render_ex_edit_view()
    {
        global $wpdb;
        $ex_id = intval($_GET['id']);
        $exercise = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}zb_exercises WHERE id = %d", $ex_id));

        if (!$exercise) {
            wp_die("Exercice introuvable.");
        }

        $questions = json_decode($exercise->exercise_data, true);
        include_once plugin_dir_path(__FILE__) . '../../admin/views/exercise-edit-page.php';
    }

    /**
     * Gère la sauvegarde de l'édition (via admin-post.php)
     */
    public function handle_save_exercise_edit()
    {
        check_admin_referer('zb_edit_ex_nonce');
        if (!current_user_can('manage_options')) wp_die('Accès refusé');

        global $wpdb;
        $ex_id = intval($_POST['exercise_id']);
        $subject_text = wp_unslash($_POST['subject_text']); // On retire les slashes magiques pour LaTeX

        $updated_questions = [];
        foreach ($_POST['questions'] as $q_data) {
            $options = array_map('wp_unslash', $q_data['options']);
            $current_answer = '';

            if ($q_data['type'] === 'multi' && isset($q_data['correct_indexes'])) {
                // On récupère toutes les options correspondant aux cases cochées
                $current_answer = [];
                foreach ($q_data['correct_indexes'] as $idx) {
                    $current_answer[] = $options[intval($idx)];
                }
            } else {
                // Choix unique classique
                $idx = intval($q_data['correct_index'] ?? 0);
                $current_answer = $options[$idx] ?? '';
            }

            $updated_questions[] = [
                'type'     => $q_data['type'],
                'question' => sanitize_textarea_field(wp_unslash($q_data['question'])),
                'options'  => array_map('sanitize_text_field', $options),
                'answer'   => $current_answer, // Stocké en array pour le multi, string pour le single
                'explanation' => sanitize_textarea_field(wp_unslash($q_data['explanation'])),
                'difficulty'  => 'Moyen'
            ];
        }

        $wpdb->update(
            $wpdb->prefix . 'zb_exercises',
            [
                'subject_text'  => sanitize_textarea_field($subject_text),
                'exercise_data' => json_encode($updated_questions, JSON_UNESCAPED_UNICODE)
            ],
            ['id' => $ex_id]
        );

        wp_redirect(admin_url('admin.php?page=zonebac-ex-bank&updated=1'));
        exit;
    }
}
