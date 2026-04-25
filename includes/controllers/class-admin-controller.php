<?php

class Zonebac_Admin_Controller
{
    use Zonebac_Generator_Form_Trait;

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
        add_action('wp_enqueue_scripts', [$this, 'enqueue_mathjax_front']);
        add_action('admin_post_zb_save_exercise_edit', [$this, 'handle_save_exercise_edit']);
        add_action('admin_post_zb_save_smart_schedule', [$this, 'handle_save_smart_schedule']);
        add_action('admin_post_zb_smart_priority_gen', [$this, 'handle_smart_priority_gen']);
        add_action('admin_post_zb_run_dispatcher_now', [$this, 'handle_run_dispatcher_now']);
        add_action('admin_post_zb_run_notion_mapping', [$this, 'handle_run_notion_mapping']);
        add_action('save_post_notion', [get_class($this), 'auto_map_new_notion'], 10, 3);
        add_action('admin_post_zb_handle_file_ingestion', [$this, 'handle_file_ingestion']);
        add_action('wp_ajax_zb_debug_analyze_step_1', [$this, 'ajax_analyze_step_1']);
        add_action('wp_ajax_zb_generate_single_exercise', [$this, 'ajax_generate_single_exercise']);
        add_action('wp_ajax_zb_get_exercise_preview', [$this, 'ajax_get_exercise_preview']);
        add_action('rest_api_init', [$this, 'handle_cors'], 15);

        add_filter('the_content', [$this, 'render_question_preview_front'], 999);
        add_filter('removable_query_args', function ($args) {
            return array_diff($args, array('zb_question_id'));
        });
    }

    public function handle_cors()
    {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', function ($value) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

            // Utilisation de constant() pour éviter l'erreur 'Undefined constant' [cite: 2025-11-16]
            $env_origins = defined('ZB_ALLOWED_ORIGINS') ? constant('ZB_ALLOWED_ORIGINS') : 'http://localhost:3000';
            $allowed_origins = explode(',', $env_origins);

            if (in_array($origin, $allowed_origins)) {
                header('Access-Control-Allow-Origin: ' . $origin);
            }

            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-ZoneBac-Key');
            return $value;
        });
    }

    public function ajax_generate_single_exercise()
    {
        check_ajax_referer('zb_ingestion_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error("Accès refusé.");

        global $wpdb;

        // 1. Correction de la récupération des IDs
        // Le JS envoie 'section_id', qui correspond à l'ID UNIQUE dans la table zb_pdf_sections
        $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
        $file_id    = intval($_POST['file_id']);

        if (!$section_id) wp_send_json_error("ID de section manquant.");

        // 2. Récupération précise de la section par son ID unique
        $section = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zb_pdf_sections WHERE id = %d AND file_id = %d",
            $section_id,
            $file_id
        ), ARRAY_A);

        if (!$section) wp_send_json_error("Section introuvable en base (ID: $section_id).");

        // 3. Composition IA (On passe bien le $file_id pour les métadonnées) [cite: 2025-11-16]
        $composed = Zonebac_Smart_Engine::compose_inspired_exercise([
            'title'   => $section['section_title'],
            'content' => $section['raw_content']
        ], $file_id);

        // Si l'IA échoue, c'est ici que l'erreur est renvoyée au JS
        if (!$composed || !isset($composed['questions'])) {
            wp_send_json_error("L'IA n'a pas pu composer l'exercice. Vérifiez vos clés API ou le format JSON de DeepSeek.");
        }

        // 4. Sauvegarde
        $wpdb->insert($wpdb->prefix . 'zb_exercises', [
            'notion_id'      => 0,
            'matiere_id'     => $file_meta->matiere_id ?? 0,
            'classe_id'      => $file_meta->classe_id ?? 0,
            'title'          => $composed['exercise_title'] ?? $section['section_title'],
            'subject_text'   => $composed['subject_text'],
            'exercise_data'  => json_encode($composed['questions'], JSON_UNESCAPED_UNICODE),
            'total_points'   => $composed['total_calculated_points'] ?? 0,
            'origin_file_id' => $file_id,
            'difficulty'     => $composed['global_difficulty'] ?? 'Moyen'
        ]);

        $new_id = $wpdb->insert_id;
        $preview_url = admin_url('admin.php?page=zonebac-ex-gen&preview_exercise=' . $new_id);

        wp_send_json_success([
            'points' => $composed['total_calculated_points'],
            'url'    => $preview_url,
            'id'     => $new_id
        ]);
    }

    public function ajax_analyze_step_1()
    {
        set_time_limit(180);
        $file_id = intval($_POST['file_id'] ?? 0);
        check_ajax_referer('zb_ingestion_nonce', 'nonce');

        global $wpdb;
        $table_sections = $wpdb->prefix . 'zb_pdf_sections';

        // 1. TENTATIVE DE RÉCUPÉRATION DEPUIS LA BD [cite: 2026-03-21]
        $sections = $wpdb->get_results($wpdb->prepare(
            "SELECT id, section_title as title, raw_content as content FROM $table_sections WHERE file_id = %d",
            $file_id
        ), ARRAY_A);

        if (empty($sections)) {
            // 2. SI VIDE : LANCEMENT DU FLUX IA COMPLET [cite: 2026-03-21]
            error_log("ZB DEBUG: Aucune donnée en BD. Lancement de l'extraction IA...");
            $raw_text = Zonebac_Smart_Engine::analyze_pdf_content_step_1($file_id);

            // Cette fonction insère en BD mais retourne un tableau sans les nouveaux IDs [cite: 2025-11-16]
            Zonebac_Smart_Engine::identify_sections_only($raw_text, $file_id);

            // ÉTAPE CRUCIALE : On recharge les données depuis la BD pour obtenir les IDs réels [cite: 2026-03-21]
            $sections = $wpdb->get_results($wpdb->prepare(
                "SELECT id, section_title as title, raw_content as content FROM $table_sections WHERE file_id = %d",
                $file_id
            ), ARRAY_A);
        }

        if (empty($sections)) {
            wp_send_json_error("L'IA n'a détecté aucune section dans ce document.");
        }

        // 3. AFFICHAGE DU RENDU (Utilisation sécurisée de $sec['id']) [cite: 2025-11-16]
        ob_start();
?>
        <div class="zb-preview-container" style="background: #0f172a; padding: 25px; border-radius: 12px;">
            <h2 style="color: #38bdf8; border-bottom: 2px solid #38bdf8; padding-bottom: 15px;">
                <span class="dashicons dashicons-database-import"></span>
                Sections Extraites (<?php echo count($sections); ?>)
            </h2>

            <div class="zb-sections-grid" style="display: grid; gap: 20px; margin-top: 20px;">
                <?php foreach ($sections as $sec) : ?>
                    <div class="zb-section-card" style="background: #1e293b; border: 1px solid #334155; border-radius: 10px; overflow: hidden;">
                        <div style="background: #334155; padding: 10px 20px; color: #38bdf8; font-weight: bold; display: flex; justify-content: space-between;">
                            <span><?php echo esc_html($sec['title']); ?></span>
                            <button class="button button-primary btn-generate-ex"
                                data-section-id="<?php echo esc_attr($sec['id']); ?>"
                                style="background: #10b981; border: none; font-size: 11px;">
                                Générer 10 Questions
                            </button>
                        </div>
                        <div style="padding: 15px; color: #cbd5e1; font-family: monospace; font-size: 12px; max-height: 150px; overflow-y: auto;">
                            <?php echo nl2br(htmlspecialchars($sec['content'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
<?php
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    public function handle_file_ingestion()
    {
        global $wpdb;
        check_admin_referer('zb_ingestion_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }

        // Récupération des tableaux de métadonnées envoyés par le formulaire [cite: 2025-11-16]
        $origins     = $_POST['file_origins'] ?? [];
        $matiere_ids = $_POST['file_matiere_ids'] ?? [];
        $classe_ids  = $_POST['file_classe_ids'] ?? []; // RÉCUPÉRATION DE LA CLASSE [cite: 2025-11-16]
        $types       = $_POST['file_types'] ?? [];
        $files       = $_FILES['zb_archives'] ?? [];

        if (!empty($files['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');

            // On boucle sur chaque fichier pour un traitement individuel [cite: 2025-11-16]
            foreach ($files['name'] as $key => $name) {
                if ($files['error'][$key] !== UPLOAD_ERR_OK) continue;

                // Préparation des données pour wp_handle_upload
                $file_data = [
                    'name'     => $files['name'][$key],
                    'type'     => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error'    => $files['error'][$key],
                    'size'     => $files['size'][$key]
                ];

                $upload = wp_handle_upload($file_data, ['test_form' => false]);

                if ($upload && !isset($upload['error'])) {
                    // INSERTION DYNAMIQUE (Utilisation de classe_id) [cite: 2026-03-21]
                    $wpdb->insert($wpdb->prefix . 'zb_file_ingestion', [
                        'file_name'   => $name,
                        'file_path'   => $upload['file'],
                        'matiere_id'  => intval($matiere_ids[$key]),
                        'classe_id'   => intval($classe_ids[$key]), // SAUVEGARDE DE LA CLASSE [cite: 2026-03-21]
                        'origin_info' => sanitize_text_field($origins[$key] . ' - ' .  ($types[$key] ?? 'Bac')),
                        'status'      => 'pending',
                        'created_at'  => current_time('mysql')
                    ]);
                }
            }
        }

        // Redirection vers l'onglet Ingestion avec l'ancre active
        wp_redirect(admin_url('admin.php?page=zonebac-ex-gen&success=1#ingestion-mode'));
        exit;
    }
    public static function auto_map_new_notion($post_id, $post, $update)
    {
        // On ne mappe que si c'est une publication réelle (pas un brouillon/auto-save)
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_status !== 'publish') return;

        require_once plugin_dir_path(__FILE__) . 'class-smart-engine.php';

        // On lance le mapping uniquement pour cette nouvelle notion [cite: 2025-11-16]
        Zonebac_Smart_Engine::map_notions_relations_with_ai($post_id);
    }

    public function handle_run_notion_mapping()
    {
        error_log("Zonebac Debug: L'action Mapping a bien été reçue !"); // TEST 1

        check_admin_referer('zb_run_mapping_nonce');
        if (!current_user_can('manage_options')) wp_die('Accès refusé');

        $file_path = plugin_dir_path(__FILE__) . 'class-smart-engine.php';
        if (file_exists($file_path)) {
            require_once $file_path;
            error_log("Zonebac Debug: Fichier Smart Engine chargé."); // TEST 2
            Zonebac_Smart_Engine::map_notions_relations_with_ai();
        } else {
            error_log("Zonebac Error: Fichier class-smart-engine.php introuvable à " . $file_path);
        }

        wp_redirect(admin_url('admin.php?page=zonebac-ex-gen&message=success#smart-mode'));
        exit;
    }

    public function handle_save_dispatcher_status()
    {
        check_admin_referer('zb_dispatcher_status_nonce');
        if (!current_user_can('manage_options')) return;

        $settings = Zonebac_Settings_Model::get_settings();
        $settings['enable_smart_dispatcher'] = isset($_POST['enable_smart_dispatcher']) ? 'yes' : 'no';

        update_option('zb_lms_settings', $settings);

        wp_redirect(admin_url('admin.php?page=zonebac-ex-gen&message=Status mis à jour#smart-mode'));
        exit;
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
                echo '<div class="zb-exercise-preview" style="max-width:800px; margin:auto; font-family: sans-serif;">';

                // 1. TITRE DE L'EXERCICE
                echo '<h1 style="color: #1e293b; margin-bottom: 10px;">' . esc_html($exercise->title) . '</h1>';

                // 2. BADGE DE DIFFICULTÉ ET SCORE TOTAL (L'ajout demandé) [cite: 2025-11-16]
                $color_map = ['Facile' => '#22c55e', 'Moyen' => '#f59e0b', 'Difficile' => '#ef4444'];
                $diff_color = $color_map[$exercise->difficulty] ?? '#64748b';

                echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 10px 0; border-bottom: 1px solid #e2e8f0;">';
                echo '<span style="background:' . $diff_color . '; color:white; padding:6px 15px; border-radius:20px; font-weight:bold; font-size:13px; text-transform: uppercase;">';
                echo 'Niveau : ' . esc_html($exercise->difficulty);
                echo '</span>';
                echo '<span style="font-weight: bold; color: #0ea5e9; font-size:16px;">';
                echo 'Barème Total : ' . intval($exercise->total_points) . ' points';
                echo '</span>';
                echo '</div>';

                // 3. ÉNONCÉ / SUJET
                echo '<div class="zb-subject" style="background:#fff; padding:25px; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:30px; box-shadow:0 4px 6px rgba(0,0,0,0.05); line-height: 1.6;">';
                echo '<strong style="display:block; margin-bottom:10px; color: #64748b; text-transform: uppercase; font-size: 12px;">Énoncé du sujet</strong>';
                echo wpautop($exercise->subject_text);
                echo '</div>';

                // 4. BOUCLE DES QUESTIONS
                foreach ($questions as $i => $q) {
                    echo '<div class="zb-q-item" style="margin-bottom:40px; padding:25px; background:#f8fafc; border-left:5px solid #0073aa; border-radius: 0 12px 12px 0; box-shadow:0 2px 4px rgba(0,0,0,0.05);">';
                    echo '<h3 style="display: flex; justify-content: space-between; align-items: center; margin-top: 0;">';
                    echo '<span>Question ' . ($i + 1) . ' <span style="font-size:0.7em; color:#64748b; font-weight: normal;">(' . esc_html($q['type'] ?? 'single') . ')</span></span>';
                    echo '<span style="font-size:0.7em; background:#fff; color: #0073aa; padding:4px 10px; border-radius:6px; border:1px solid #0073aa;">' . intval($q['points'] ?? 1) . ' pts</span>';
                    echo '</h3>';

                    echo '<div style="margin-bottom: 20px; font-size: 16px;">' . wpautop($q['question']) . '</div>';

                    echo '<div class="zb-options-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">';
                    foreach ($q['options'] as $idx => $opt) {
                        $is_correct = false;
                        if (is_array($q['answer'])) {
                            $is_correct = in_array($opt, $q['answer']);
                        } else {
                            $is_correct = ($opt === $q['answer']);
                        }

                        $bg = $is_correct ? '#dcfce7' : '#fff';
                        $border = $is_correct ? '#22c55e' : '#cbd5e1';
                        $color = $is_correct ? '#166534' : '#1e293b';

                        echo '<div style="padding:12px; border:1px solid ' . $border . '; background:' . $bg . '; color:' . $color . '; border-radius:8px; font-size: 14px;">';
                        echo '<strong style="margin-right:8px;">' . chr(65 + $idx) . '.</strong> ' . esc_html($opt);
                        if ($is_correct) echo ' <span style="float:right;">✅</span>';
                        echo '</div>';
                    }
                    echo '</div>';

                    echo '<div style="margin-top:20px; padding: 15px; background:#eff6ff; border-radius: 8px; font-size:14px; color:#1e40af; border: 1px solid #dbeafe;">';
                    echo '<strong>Correction détaillée :</strong> ' . wpautop($q['explanation']);
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
                filemtime($script_file),
                true
            );

            // UN SEUL APPEL : On regroupe tout ici [cite: 2025-11-16]
            wp_localize_script('zonebac-admin-js', 'zbData', [
                'rest_url' => get_rest_url(),
                'nonce'    => wp_create_nonce('zb_ingestion_nonce') // Ce nom doit correspondre au check_ajax_referer
            ]);
        }

        // 3. MathJax (Chargement avec priorité haute)
        wp_enqueue_script(
            'mathjax-lib',
            'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js',
            [],
            null,
            false // On le charge dans le <head> pour être sûr qu'il soit prêt
        );

        // Configuration explicite AVANT le chargement du script
        wp_add_inline_script('mathjax-lib', "
        window.MathJax = {
                tex: {
                    inlineMath: [['$', '$'], ['\\\\(', '\\\\)']],
                    displayMath: [['$$', '$$'], ['\\\\[', '\\\\]']]
                },
                options: {
                    skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre']
                },
                startup: {
                    pageReady: () => {
                        console.log('ZB DEBUG: Bibliothèque MathJax chargée et prête.');
                        return MathJax.startup.defaultPageReady();
                    }
                }
        };
        ", 'before');
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
        if (!current_user_can('manage_options')) wp_die('Accès refusé');

        // On récupère les deux clés du formulaire [cite: 2025-11-16]
        $data = [
            'deepseek_key' => sanitize_text_field($_POST['deepseek_key']),
            'gemini_key'   => sanitize_text_field($_POST['gemini_key'])
        ];

        // On envoie le tableau complet au modèle [cite: 2025-11-16]
        Zonebac_Settings_Model::save_settings($data);

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

    /**
     * Vérifie si la clé X-ZoneBac-Key est présente et valide
     */
    public function check_internal_key($request)
    {
        $client_key = $request->get_header('X-ZoneBac-Key');
        $server_key = defined('ZONEBAC_INTERNAL_KEY') ? constant('ZONEBAC_INTERNAL_KEY') : null;

        if (!$server_key) {
            error_log("ZONEBAC SECURITY ERROR: Clé manquante dans wp-config.php");
            return false;
        }

        return $client_key === $server_key;
    }

    public function register_rest_routes()
    {
        register_rest_route('zonebac/v1', '/get-hierarchy', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_hierarchy_data'],
            'permission_callback' => [$this, 'check_internal_key']
        ]);

        register_rest_route('zonebac/v1', '/exercices', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_external_exercises'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('zonebac/v1', '/questions', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_external_questions'],
            'permission_callback' => [$this, 'check_internal_key']
        ]);

        register_rest_route('zonebac/v1', '/quick-quiz', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_quick_quiz_data'],
            'permission_callback' => [$this, 'check_internal_key']
        ]);

        register_rest_route('zonebac/v1', '/filter-data', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_dynamic_filter_data'],
            'permission_callback' => [$this, 'check_internal_key']
        ]);

        register_rest_route('zonebac/v1', '/classes', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_public_classes'],
            'permission_callback' => '__return_true'
        ]);
    }
    public function get_public_classes()
    {
        // On récupère les termes de la taxonomie 'classe'
        $terms = get_terms([
            'taxonomy'   => 'classe',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return new WP_Error('no_classes', 'Aucune classe trouvée', ['status' => 404]);
        }

        return array_map(function ($t) {
            return [
                'id'   => (string)$t->term_id,
                'nom'  => $t->name
            ];
        }, $terms);
    }
    public function get_dynamic_filter_data($request)
    {
        $type = sanitize_text_field($request->get_param('type'));
        $parent_id = intval($request->get_param('parent_id'));
        $data = [];

        // Mapping des types vers tes taxonomies réelles
        $tax_map = [
            'classes'   => 'classe',
            'matieres'  => 'matiere',
            'chapitres' => 'chapitre'
        ];

        if ($type === 'notions') {
            $posts = get_posts([
                'post_type' => 'notion',
                'posts_per_page' => -1,
                'tax_query' => [['taxonomy' => 'chapitre', 'field' => 'term_id', 'terms' => $parent_id]]
            ]);
            foreach ($posts as $p) $data[] = ['id' => (string)$p->ID, 'label' => $p->post_title];
        } else {
            $taxonomy = $tax_map[$type] ?? '';
            if (!$taxonomy) return new WP_Error('error', 'Type inconnu');

            $args = ['taxonomy' => $taxonomy, 'hide_empty' => false];
            if ($parent_id > 0) {
                $args['meta_query'] = [['key' => 'parent_id', 'value' => $parent_id]];
            }

            $terms = get_terms($args);
            if (!is_wp_error($terms)) {
                foreach ($terms as $t) $data[] = ['id' => (string)$t->term_id, 'label' => $t->name];
            }
        }
        return new WP_REST_Response($data, 200);
    }

    public function get_quick_quiz_data()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_questions';

        // On pioche 10 questions aléatoires pour le mode "Flash" [cite: 2025-11-16, 2026-04-05]
        $results = $wpdb->get_results("SELECT question_data FROM $table ORDER BY RAND() LIMIT 10");

        return array_map(function ($q) {
            $data = json_decode($q->question_data, true);
            return [
                'text'        => $data['question'] ?? '',
                'options'     => $data['options'] ?? [],
                'correct'     => [array_search($data['answer'], $data['options'])], // Conversion index [cite: 2026-04-05]
                'explanation' => $data['explanation'] ?? '',
                'level'       => $data['difficulty'] ?? 'Moyen',
                'point'       => 1
            ];
        }, $results);
    }

    public function get_external_exercises($request)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_exercises';

        // 2. RÉCUPÉRATION DES FILTRES [cite: 2026-04-06]
        $notion_id = intval($request->get_param('notion'));
        $avg_score = $request->get_param('avg_score'); // On le garde tel quel pour tester le null [cite: 2026-04-06]

        // 3. PARAMÈTRES DE PAGINATION (Crucial pour l'Infinite Scroll) [cite: 2026-04-06]
        // Si per_page n'est pas fourni, on utilise 6 (comme dans ton page.tsx)
        $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 6;
        $page     = $request->get_param('page') ? intval($request->get_param('page')) : 1;
        $offset   = ($page - 1) * $per_page;

        // 4. CONSTRUCTION DE LA REQUÊTE [cite: 2025-11-16]
        $query = "SELECT * FROM $table WHERE 1=1";

        if ($notion_id > 0) {
            $query .= $wpdb->prepare(" AND notion_id = %d", $notion_id);
        }

        if ($avg_score !== null && $avg_score !== '') {
            $query .= $wpdb->prepare(" AND total_points >= %d", intval($avg_score));
        }

        // 5. AJOUT DE LA LOGIQUE DE PAGINATION SQL [cite: 2025-11-16]
        // On trie par ID décroissant pour avoir les nouveautés en premier [cite: 2026-04-06]
        $sql = $query . $wpdb->prepare(" ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset);

        $results = $wpdb->get_results($sql);

        // 6. GESTION DES ERREURS SQL [cite: 2025-11-16]
        if ($wpdb->last_error) {
            error_log("ZB API ERROR - SQL : " . $wpdb->last_error);
            return new WP_REST_Response(['error' => $wpdb->last_error], 500);
        }

        // 7. FORMATAGE POUR NEXT.JS [cite: 2026-04-05, 2026-04-06]
        $formatted = [];
        foreach ($results as $ex) {
            error_log("ZB DEBUG - Envoi du sujet pour l'ID " . $ex->id . " : " . substr($ex->subject_text, 0, 50) . "...");
            $questions = json_decode($ex->exercise_data, true);

            // Récupération dynamique du nom du chapitre
            $chapter_name = "Annales"; // Valeur par défaut

            if ($ex->notion_id > 0) {
                // On va chercher le terme de la taxonomie 'chapitre' lié à cette notion
                $terms = get_the_terms($ex->notion_id, 'chapitre');
                if (!is_wp_error($terms) && !empty($terms)) {
                    $chapter_name = $terms[0]->name;
                }
            }

            $formatted[] = [
                'id'        => (string)$ex->id,
                'type'      => 'exercice',
                'titre'     => $ex->title,
                'level'     => $ex->difficulty ?? 'Moyen',
                'chapitre'  => $chapter_name, //$ex->chapitre ?? 'Annales',
                'subject_text' => $ex->subject_text,
                'questions' => is_array($questions) ? $questions : [],
                'notionId'     => (string)$ex->notion_id,
                'matiereId'    => (string)($ex->matiere_id ?? ''),
                'chapitreId'   => (string)($ex->chapitre_id ?? ''),
                'classeId'     => (string)($ex->classe_id ?? '')
            ];
        }

        return new WP_REST_Response($formatted, 200);
    }
    public function get_external_questions($request)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_questions';

        // Paramètres de filtrage et pagination
        $notion_id = intval($request->get_param('notion'));
        $per_page  = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 10;
        $page      = $request->get_param('page') ? intval($request->get_param('page')) : 1;
        $offset    = ($page - 1) * $per_page;

        $query = "SELECT * FROM $table WHERE status = 'completed'";

        if ($notion_id > 0) {
            $query .= $wpdb->prepare(" AND notion_id = %d", $notion_id);
        }

        $sql = $query . $wpdb->prepare(" ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset);
        $results = $wpdb->get_results($sql);

        $formatted = [];
        foreach ($results as $q) {
            $data = json_decode($q->question_data, true);
            $formatted[] = [
                'id'          => (string)$q->id,
                'notion_id'   => $q->notion_id,
                'difficulty'  => $q->difficulty,
                'points'      => $q->points,
                'question'    => $data['question'] ?? '',
                'options'     => $data['options'] ?? [],
                'answer'      => $data['answer'] ?? '',
                'explanation' => $data['explanation'] ?? '',
            ];
        }

        return new WP_REST_Response($formatted, 200);
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
        require_once plugin_dir_path(__FILE__) . 'class-smart-engine.php';

        $classes = get_terms(['taxonomy' => 'classe', 'hide_empty' => false]);
        $smart_engine = new Zonebac_Smart_Engine();
        $stats_notions = [];

        // On récupère la matière depuis l'URL (GET)
        $selected_matiere = isset($_GET['view_matiere']) ? intval($_GET['view_matiere']) : 0;

        if ($selected_matiere > 0) {
            // IMPORTANT : On passe l'ID récupéré à la méthode
            $stats_notions = $smart_engine->get_priority_notions($selected_matiere, 50);
        }

        // Le reste du code (schedules, tables, etc.)
        $schedules = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}zb_smart_schedules");
        require_once plugin_dir_path(__FILE__) . 'class-exercise-job-table.php';
        $ex_job_table = new Zonebac_Exercise_Job_Table();
        $ex_job_table->prepare_items();

        include_once plugin_dir_path(__FILE__) . '../../admin/views/exercise-generator-page.php';
    }

    public function render_ex_bank_view()
    {
        // 1. Chargement de la classe de la table
        require_once plugin_dir_path(__FILE__) . 'class-exercise-bank-table.php';

        // 2. Initialisation : ATTENTION au nom de la variable ($bank_table)
        $bank_table = new Zonebac_Exercise_Bank_Table();

        // 3. Préparation des éléments (Important pour éviter le Fatal Error)
        $bank_table->prepare_items();

        // 4. Inclusion de la vue
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
                'points' => intval($q_data['points']),
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

    /**
     * Enregistre ou met à jour un planning de génération intelligente
     */
    public function handle_save_smart_schedule()
    {
        check_admin_referer('zb_smart_sched_nonce');
        if (!current_user_can('manage_options')) wp_die('Accès refusé');

        global $wpdb;
        $matiere_id  = intval($_POST['matiere_id']);
        $threshold_n = intval($_POST['threshold_n']);
        $frequency   = sanitize_text_field($_POST['frequency']);

        // Calcul du premier run : On commence dans 5 minutes pour test
        $next_run = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $table = $wpdb->prefix . 'zb_smart_schedules';

        // On vérifie si un planning existe déjà pour cette matière pour éviter les doublons
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE matiere_id = %d", $matiere_id));

        if ($existing) {
            $wpdb->update($table, [
                'threshold_n' => $threshold_n,
                'frequency'   => $frequency,
                'is_active'   => 1
            ], ['id' => $existing]);
        } else {
            $wpdb->insert($table, [
                'matiere_id'  => $matiere_id,
                'threshold_n' => $threshold_n,
                'frequency'   => $frequency,
                'next_run'    => $next_run,
                'is_active'   => 1
            ]);
        }

        wp_redirect(admin_url('admin.php?page=zonebac-ex-gen&smart_sched_updated=1#smart-mode'));
        exit;
    }

    /**
     * Lance une génération d'urgence pour une notion spécifique depuis le tableau des Gaps
     */
    public function handle_smart_priority_gen()
    {
        check_admin_referer('zb_priority_gen_nonce');
        if (!current_user_can('manage_options')) wp_die('Accès refusé');

        $notion_id = intval($_POST['notion_id']);

        global $wpdb;
        // On ajoute un job dans la file d'attente (Asynchrone)
        $wpdb->insert($wpdb->prefix . 'zb_exercise_jobs', [
            'notion_id'  => $notion_id,
            'count'      => 10, // On génère par packs de 10 pour ne pas surcharger
            'status'     => 'pending',
            'created_at' => current_time('mysql')
        ]);

        // On redirige vers l'onglet Smart avec un message de succès
        wp_redirect(add_query_arg(['page' => 'zonebac-ex-gen', 'message' => 'Job ajouté à la file d\'attente !'], admin_url('admin.php')) . '#smart-mode');
        exit;
    }

    public function handle_run_dispatcher_now()
    {
        check_admin_referer('zb_run_dispatch_nonce');
        if (!current_user_can('manage_options')) wp_die('Accès refusé');

        require_once plugin_dir_path(__FILE__) . 'class-smart-engine.php';
        Zonebac_Smart_Engine::run_auto_dispatcher();

        // On force la redirection avec l'ancre #smart-mode pour éviter la page blanche [cite: 2025-11-16]
        wp_redirect(add_query_arg([
            'page'         => 'zonebac-ex-gen',
            'message'      => 'success', // Ce code sera traduit par la vue
            'message_type' => 'updated' // 'updated' donne la couleur verte de WordPress
        ], admin_url('admin.php')) . '#smart-mode');
        exit;
        // wp_redirect(admin_url('admin.php?page=zonebac-ex-gen&message=success#smart-mode'));
        // exit;
    }

    public function rest_generate_single_exercise($request)
    {
        $section_index = intval($request->get_param('section_index'));
        $file_id       = intval($request->get_param('file_id'));

        global $wpdb;

        // 1. Récupération de la section en BD [cite: 2026-03-21]
        $section = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zb_pdf_sections WHERE file_id = %d LIMIT %d, 1",
            $file_id,
            $section_index
        ), ARRAY_A);

        if (!$section) {
            return new WP_Error('no_section', 'Section introuvable en base de données.', ['status' => 404]);
        }

        // 2. Composition via l'IA [cite: 2026-03-21]
        $composed = Zonebac_Smart_Engine::compose_inspired_exercise([
            'title'   => $section['section_title'],
            'content' => $section['raw_content']
        ]);

        if (!$composed) {
            return new WP_Error('ai_error', 'L\'IA n\'a pas pu générer l\'exercice.', ['status' => 500]);
        }

        // 3. Sauvegarde finale [cite: 2026-03-21]
        $wpdb->insert($wpdb->prefix . 'zb_exercises', [
            'title'          => $composed['exercise_title'] ?? $section['section_title'],
            'subject_text'   => $composed['subject_text'],
            'exercise_data'  => json_encode($composed['questions'], JSON_UNESCAPED_UNICODE),
            'total_points'   => $composed['total_calculated_points'],
            'difficulty'     => $composed['global_difficulty'] ?? 'Moyen',
            'origin_file_id' => $file_id
        ]);

        return new WP_REST_Response(['success' => true, 'points' => $composed['total_calculated_points']], 200);
    }

    public function ajax_get_exercise_preview()
    {
        check_ajax_referer('zb_preview_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        global $wpdb;
        $id = intval($_POST['id']);
        $exercise = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}zb_exercises WHERE id = %d", $id));

        if ($exercise) {
            // On réutilise ta logique de rendu de prévisualisation existante
            $_GET['preview_exercise'] = $id;
            echo $this->render_question_preview_front('');
        } else {
            echo "Exercice introuvable.";
        }
        wp_die();
    }

    /**
     * Sauvegarde un exercice en héritant de la hiérarchie de la notion
     */
    public function save_new_exercise($notion_id, $title, $data)
    {
        global $wpdb;

        // 1. On récupère la hiérarchie parente depuis la notion [cite: 2025-11-16]
        // Note : Tu devras adapter cette requête selon ta table de notions/taxonomies
        $hierarchy = $wpdb->get_row($wpdb->prepare(
            "SELECT matiere_id, chapitre_id, classe_id FROM {$wpdb->prefix}zb_notions WHERE id = %d",
            $notion_id
        ));

        // 2. On insère l'exercice avec TOUTES ses étiquettes [cite: 2026-04-18]
        $wpdb->insert(
            $wpdb->prefix . 'zb_exercises',
            array(
                'notion_id'    => $notion_id,
                'matiere_id'   => $hierarchy->matiere_id ?? null,
                'chapitre_id'  => $hierarchy->chapitre_id ?? null,
                'classe_id'    => $hierarchy->classe_id ?? null,
                'title'        => $title,
                'exercise_data' => json_encode($data),
                'created_at'   => current_time('mysql')
            )
        );
    }
}
