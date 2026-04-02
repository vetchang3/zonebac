<?php if (!defined('ABSPATH')) exit; ?>

<style>
    /* Modification ciblée dans exercise-generator-page.php */
    #debug-extraction-output {
        border: 5px solid yellow !important;
        /* Bordure de debug pour confirmer le chargement */
        width: 100% !important;
        min-width: 100% !important;
        min-height: 800px !important;
        display: block !important;
    }

    .zb-preview-container {
        width: 100% !important;
        max-width: 100% !important;
    }

    /* On s'assure que le conteneur parent ne bloque pas la largeur */
    #zb-debug-extraction-zone {
        width: 98vw !important;
        margin-left: calc(-50vw + 50%) !important;
        position: relative !important;
        left: 50% !important;
        right: 50% !important;
    }

    /* STRUCTURE GLOBALE */
    .zb-final-container {
        width: calc(100% - 20px);
        margin: 20px 0;
        display: flex !important;
        gap: 25px;
        align-items: flex-start;
    }

    .zb-col-30 {
        flex: 0 0 350px;
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    .zb-col-70 {
        flex: 1;
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        border: 1px solid #e2e8f0;
        max-height: 550px;
        overflow-y: auto;
    }

    /* TABLEAUX ET STYLES */
    .zb-col-70 table.wp-list-table td {
        padding: 8px 10px !important;
        vertical-align: middle;
    }

    .zb-col-70 thead th {
        position: sticky;
        top: 0;
        background: #f8fafc;
        z-index: 20;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
    }

    /* ONGLETS */
    .zb-tab-content {
        display: none;
        animation: fadeIn 0.3s;
        width: 100%;
    }

    .zb-tab-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    /* TABLE DE SUIVI EN BAS */
    .zb-jobs-monitor {
        margin-top: 40px;
        background: white;
        padding: 20px;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
    }

    /* FORMULAIRE   */
    .zb-form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }

    .zb-form-group {
        flex: 1;
        min-width: 140px;
    }

    .zb-form-group label {
        display: block;
        margin-bottom: 5px;
        font-size: 13px;
        color: #1e293b;
    }

    .zb-form-group select,
    .zb-form-group input {
        width: 100%;
        height: 35px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
    }

    .zb-btn-submit {
        height: 35px !important;
        background: #163A5E !important;
        border-color: #163A5E !important;
        padding: 0 20px !important;
    }

    /*===========================*/
    /* Style du Toggle Switch */
    .zb-toggle-wrapper {
        display: flex;
        align-items: center;
        gap: 15px;
        margin: 20px 0;
    }

    .zb-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
    }

    .zb-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .zb-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .4s;
        border-radius: 34px;
    }

    .zb-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked+.zb-slider {
        background-color: #10b981;
        /* Vert Production */
    }

    input:focus+.zb-slider {
        box-shadow: 0 0 1px #10b981;
    }

    input:checked+.zb-slider:before {
        transform: translateX(24px);
    }

    /* Correction de la marge gauche et centrage */
    #zb-full-width-test {
        /* On abandonne le calcul vw qui ignore le menu latéral */
        width: 96% !important;
        margin: 20px auto !important;
        /* Centre automatiquement dans l'espace disponible */

        /* On s'assure qu'il n'y a pas de flottement ou de position relative gênante */
        position: relative !important;
        left: 0 !important;
        right: 0 !important;

        background: #1e293b !important;
        color: #ffffff !important;
        border: 2px solid #334155 !important;
        border-radius: 12px !important;
        box-sizing: border-box !important;

        height: 500px !important;
        overflow-y: auto !important;
        box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2) !important;
    }

    /* On force le conteneur parent à laisser passer la largeur */
    .wrap {
        max-width: 100% !important;
    }

    .zb-test-inner {
        width: 100% !important;
        padding: 30px !important;
        display: block !important;
        box-sizing: border-box !important;
    }

    /* On force WordPress à ne pas limiter ce conteneur précis */
    .wp-admin #zb-full-width-test {
        max-width: none !important;
    }

    /* Personnalisation de la scrollbar pour un effet moderne (Wahou) */
    #zb-full-width-test::-webkit-scrollbar {
        width: 10px;
    }

    #zb-full-width-test::-webkit-scrollbar-track {
        background: #1e293b;
    }

    #zb-full-width-test::-webkit-scrollbar-thumb {
        background: #38bdf8;
        border-radius: 5px;
    }

    /* Animation de rotation pour l'icône de chargement */
    .spin {
        animation: zb-rotate 2s infinite linear;
        display: inline-block;
    }

    @keyframes zb-rotate {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><span class="dashicons dashicons-welcome-write-blog"></span> Générateur d'Exercices</h1>
    <hr class="wp-header-end">

    <?php
    // 1. On récupère les différents signaux possibles
    $status_success = isset($_GET['success']) && $_GET['success'] == '1';
    $status_scheduled = isset($_GET['scheduled']) && $_GET['scheduled'] == '1';
    $message_code = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

    // 2. On définit le texte à afficher selon le cas
    $final_msg = "";

    if ($status_success || $status_scheduled) {
        $final_msg = "La génération manuelle a été lancée avec succès !";
    } elseif ($message_code === 'success') {
        $final_msg = "Dispatcher exécuté avec succès !";
    }

    // 3. Affichage du bandeau si un message existe
    if (!empty($final_msg)) : ?>
        <div class="notice updated is-dismissible">
            <p><strong><?php echo esc_html($final_msg); ?></strong></p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <a href="#manual-mode" class="nav-tab">Génération Manuelle</a>
        <a href="#smart-mode" class="nav-tab">Génération Intelligente & Planning</a>
        <a href="#ingestion-mode" class="nav-tab">Ingestion d'épreuves</a>

    </h2>

    <div id="manual-mode" class="zb-tab-content">
        <?php $this->render_shared_hierarchy_form('zb_do_exercise_generation', 'zb_ex_gen_action'); ?>
    </div>

    <div id="smart-mode" class="zb-tab-content">
        <div class="card zb-full-width" style="border-left: 4px solid #475569; background: #f1f5f9; padding: 20px; border-radius: 12px;">
            <h3 style="margin-top:0;"><span class="dashicons dashicons-info"></span> Guide d'utilisation stratégique</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <div>
                    <strong>1. Maillage Pédagogique</strong>
                    <p class="description">À lancer en premier pour que l'IA connaisse les liens entre les notions. C'est ce qui permet de générer des exercices de <strong>synthèse</strong>.</p>
                </div>
                <div>
                    <strong>2. Planning & Seuil</strong>
                    <p class="description">Le dispatcher choisit la notion avec le plus gros <strong>Gap</strong> (objectif 50). Il ne travaille que si le Mode Production est sur <strong>ON</strong>.</p>
                </div>
                <div>
                    <strong>3. Environnement</strong>
                    <p class="description">En local, utilise le bouton <strong>"Test du Dispatcher"</strong> pour simuler une exécution sans attendre le Cron WordPress.</p>
                </div>
            </div>
        </div>

        <div class="card" style="border-left: 4px solid #0ea5e9; background: #f8fafc; padding: 20px; margin-bottom: 20px;">
            <form method="get" style="display: flex; align-items: center; gap: 20px;">
                <input type="hidden" name="page" value="zonebac-ex-gen">
                <input type="hidden" name="smart_view" value="1">
                <select name="view_matiere" onchange="this.form.submit()" style="min-width:250px;">
                    <option value="">-- Analyser une matière --</option>
                    <?php
                    $matieres = get_terms(['taxonomy' => 'matiere', 'hide_empty' => false]);
                    foreach ($matieres as $m) : ?>
                        <option value="<?php echo $m->term_id; ?>" <?php selected(isset($_GET['view_matiere']) && $_GET['view_matiere'] == $m->term_id); ?>><?php echo esc_html($m->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="card" style="margin-bottom: 25px; border-left: 4px solid #10b981; background: #f0fdf4; padding: 15px; border-radius: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin:0; color: #166534;"><span class="dashicons dashicons-performance"></span> Test du Dispatcher Automatique</h4>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="zb_run_dispatcher_now">
                    <?php wp_nonce_field('zb_run_dispatch_nonce'); ?>
                    <button type="submit" class="button button-primary" style="background: #10b981; border-color: #059669;">Lancer l'analyse et créer les jobs</button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-bottom: 25px; border-left: 4px solid #6366f1; background: #f5f3ff; padding: 15px; border-radius: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h4 style="margin:0; color: #4338ca;"><span class="dashicons dashicons-networking"></span> Initialisation du Maillage IA</h4>
                    <p class="description" style="margin:5px 0 0 0;">Analyse les notions pour identifier les pré-requis et créer des exercices transversaux.</p>
                </div>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="zb_run_notion_mapping">
                    <?php wp_nonce_field('zb_run_mapping_nonce'); ?>
                    <button type="submit" class="button button-secondary" style="border-color: #6366f1; color: #4338ca;">Lancer le scan (5 notions)</button>
                </form>
            </div>
        </div>



        <?php
        $settings = Zonebac_Settings_Model::get_settings();
        $is_enabled = ($settings['enable_smart_dispatcher'] ?? 'no') === 'yes';
        ?>

        <div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; background: #fff;">
            <h3 style="margin-top:0;"><span class="dashicons dashicons-admin-settings"></span> Contrôle du Moteur Intelligent</h3>

            <form method="post" action="admin-post.php">
                <input type="hidden" name="action" value="zb_save_dispatcher_status">
                <?php wp_nonce_field('zb_dispatcher_status_nonce'); ?>

                <div class="zb-toggle-wrapper">
                    <label class="zb-switch">
                        <input type="checkbox" name="enable_smart_dispatcher" value="yes" <?php checked($is_enabled); ?>>
                        <span class="zb-slider"></span>
                    </label>
                    <div>
                        <strong style="font-size: 1.1em; display: block;">
                            <?php echo $is_enabled ? "Mode Production Actif" : "Mode Développement (Pause)"; ?>
                        </strong>
                        <span class="description">
                            <?php echo $is_enabled
                                ? "Le dispatcher analyse les Gaps et sollicite l'IA selon le planning."
                                : "L'IA ne sera jamais sollicitée automatiquement en arrière-plan."; ?>
                        </span>
                    </div>
                </div>

                <button type="submit" class="button button-primary" style="background: #6366f1; border-color: #4f46e5;">
                    Enregistrer la configuration
                </button>
            </form>
        </div>



        <div class="zb-final-container">
            <div class="zb-col-30">
                <h3>Planning Automatique</h3>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="zb_save_smart_schedule">
                    <?php wp_nonce_field('zb_smart_sched_nonce'); ?>
                    <p>Matière :<br><select name="matiere_id" style="width:100%;"><?php foreach ($matieres as $m) echo "<option value='{$m->term_id}'>{$m->name}</option>"; ?></select></p>
                    <p>Seuil $n$ :<br><input type="number" name="threshold_n" value="50" style="width:100%;"></p>
                    <p>Fréquence d'analyse :<br>
                        <select name="frequency" style="width:100%;">
                            <option value="hourly">Toutes les heures</option>
                            <option value="daily">Une fois par jour</option>
                            <option value="weekly">Une fois par semaine</option>
                        </select>
                    </p>
                    <?php submit_button('Enregistrer le planning'); ?>
                </form>
            </div>
            <div class="zb-col-70">
                <h3>État de Santé des Notions</h3>
                <?php if (!empty($stats_notions)) : ?>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>Notion</th>
                                <th>Progrès</th>
                                <th style="text-align:right;">Gap</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats_notions as $stat) :
                                $percent = min(100, ($stat['count'] / 50) * 100);
                                $color = ($percent < 30) ? '#ef4444' : (($percent < 70) ? '#f59e0b' : '#22c55e');
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($stat['name']); ?></strong></td>
                                    <td>
                                        <div style="background:#f1f5f9; border-radius:10px; height:8px; width:100%; overflow:hidden;">
                                            <div style="background:<?php echo $color; ?>; width:<?php echo $percent; ?>%; height:100%;"></div>
                                        </div>
                                    </td>
                                    <td style="text-align:right;"><strong><?php echo $stat['gap']; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: echo "<p class='description'>Veuillez sélectionner une matière ci-dessus.</p>";
                endif; ?>
            </div>
        </div>
    </div>

    <div id="ingestion-mode" class="zb-tab-content">
        <div class="card" style="border-left: 4px solid #8b5cf6; padding: 25px; border-radius: 12px; background: #fff; max-width: 100%;">
            <h3><span class="dashicons dashicons-upload"></span> Ingestion massive d'épreuves (Archives)</h3>
            <p class="description" style="margin-bottom: 20px;">Déposez vos fichiers PDF. Vous pourrez ensuite spécifier l'origine et la matière pour chaque document avant l'analyse.</p>

            <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="zb_handle_file_ingestion">
                <?php wp_nonce_field('zb_ingestion_nonce'); ?>

                <div style="background: #f8fafc; border: 2px dashed #cbd5e1; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 20px;">
                    <input type="file" id="zb_multi_file_input" name="zb_archives[]" multiple accept=".pdf" style="display:none;">
                    <button type="button" class="button button-secondary" onclick="document.getElementById('zb_multi_file_input').click();">
                        <span class="dashicons dashicons-cloud-upload"></span> Choisir des fichiers PDF
                    </button>
                    <p id="file_count_display" class="description" style="margin-top:10px;">Aucun fichier sélectionné</p>
                </div>

                <div id="zb_file_meta_list" style="margin-bottom: 25px; display: none;">
                    <h4 style="border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">Configuration des documents</h4>
                    <div id="zb_files_container"></div>
                </div>

                <?php submit_button('Lancer l\'upload et l\'analyse', 'button-primary', 'submit', true, [
                    'id' => 'zb_submit_ingestion',
                    'style' => 'background:#8b5cf6; border-color:#7c3aed; display:none; width:100%;'
                ]); ?>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('zb_multi_file_input');
            const container = document.getElementById('zb_files_container');
            const metaList = document.getElementById('zb_file_meta_list');
            const submitBtn = document.getElementById('zb_submit_ingestion');
            const countDisplay = document.getElementById('file_count_display');

            // Récupération des matières via PHP pour le JS
            const matieresOptions = `<?php
                                        $matieres = get_terms(['taxonomy' => 'matiere', 'hide_empty' => false]);
                                        foreach ($matieres as $m) {
                                            echo '<option value="' . $m->term_id . '">' . esc_js($m->name) . '</option>';
                                        }
                                        ?>`;

            fileInput.addEventListener('change', function() {
                container.innerHTML = '';
                const files = Array.from(this.files);

                if (files.length > 0) {
                    metaList.style.display = 'block';
                    submitBtn.style.display = 'block';
                    countDisplay.innerText = files.length + " fichier(s) prêt(s) à l'envoi";

                    files.forEach((file, index) => {
                        const fileNameClean = file.name.replace(/\.[^/.]+$/, ""); // Supprime l'extension .pdf

                        const row = document.createElement('div');
                        row.className = 'zb-file-row';
                        row.style = "display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; background: #fff; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px; align-items: center;";

                        row.innerHTML = `
                    <div style="font-weight:bold; font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${file.name}">
                        📄 ${file.name}
                    </div>
                    <div>
                        <input type="text" name="file_origins[]" value="${fileNameClean}" placeholder="Origine / Source" style="width:100%; height:30px; font-size:12px;">
                    </div>
                    <div>
                        <select name="file_matiere_ids[]" style="width:100%; height:30px; font-size:12px;">
                            ${matieresOptions}
                        </select>
                    </div>
                `;
                        container.appendChild(row);
                    });
                } else {
                    metaList.style.display = 'none';
                    submitBtn.style.display = 'none';
                    countDisplay.innerText = "Aucun fichier sélectionné";
                }
            });
        });
    </script>

    <div id="zb-full-width-test" style="margin: 20px 0; clear: both; display:none;">
        <div class="zb-test-inner" id="zb-preview-content"> </div>
    </div>


    <div class="zb-jobs-monitor" style="margin-top:30px; border-top: 2px solid #e2e8f0; padding-top: 20px;">
        <h3><span class="dashicons dashicons-media-document"></span> Suivi de l'ingestion des archives (PDF)</h3>
        <table class="wp-list-table widefat striped" style="margin-top:15px;">
            <thead>
                <tr>
                    <th>Fichier</th>
                    <th>Origine</th>
                    <th>Statut</th>
                    <th style="text-align:center;">Date d'Upload</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                global $wpdb;
                $files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}zb_file_ingestion ORDER BY created_at DESC LIMIT 10");

                if ($files) :
                    foreach ($files as $f) :
                        $status_label = [
                            'pending'    => '<span style="color:#f59e0b;">⏳ En attente</span>',
                            'processing' => '<span style="color:#6366f1;">⚙️ Analyse...</span>',
                            'completed'  => '<span style="color:#10b981;">✅ Terminé</span>',
                            'failed'     => '<span style="color:#ef4444;">❌ Échec</span>'
                        ];
                ?>
                        <tr>
                            <td><strong><?php echo esc_html($f->file_name); ?></strong></td>
                            <td><?php echo esc_html($f->origin_info); ?></td>
                            <td><?php echo $status_label[$f->status] ?? $f->status; ?></td>
                            <td style="text-align:center;"><?php echo date_i18n('d/m/Y H:i', strtotime($f->created_at)); ?></td>
                            <td style="text-align:right;">
                                <button type="button"
                                    class="zb-btn-analyze button button-small"
                                    data-file-id="<?php echo $f->id; ?>"
                                    style="background:#8b5cf6; color:white; border:none; cursor:pointer;">
                                    <span class="dashicons dashicons-search" style="font-size:16px; margin-top:3px;"></span> Analyser (Debug Step 3.1)
                                </button>
                            </td>
                        </tr>
                    <?php endforeach;
                else : ?>
                    <tr>
                        <td colspan="5" class="description">Aucun PDF trouvé en base de données.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card" style="margin-top:20px; border-top: 4px solid #6366f1;">
        <h3><span class="dashicons dashicons-networking"></span> Dernières Connexions Pédagogiques</h3>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Notion Source</th>
                    <th>Notions Liées (Synthèse)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                global $wpdb;
                $relations = $wpdb->get_results("SELECT notion_id, GROUP_CONCAT(related_notion_id) as related FROM {$wpdb->prefix}zb_notion_relations GROUP BY notion_id ORDER BY id DESC LIMIT 5");
                foreach ($relations as $rel) : ?>
                    <tr>
                        <td><strong><?php echo get_the_title($rel->notion_id); ?></strong></td>
                        <td><?php
                            $ids = explode(',', $rel->related);
                            foreach ($ids as $id) echo '<span class="tag" style="background:#e0e7ff; padding:2px 8px; border-radius:12px; margin-right:5px;">' . get_the_title($id) . '</span>';
                            ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="zb-jobs-monitor">
        <h3><span class="dashicons dashicons-list-view"></span> Suivi de la file d'attente (Jobs)</h3>
        <?php
        // On affiche la table de suivi préparée par le contrôleur
        if (isset($ex_job_table)) {
            $ex_job_table->display();
        }
        ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- PARTIE 1 : GESTION DES ONGLETS (RESTORED) ---
        const tabs = document.querySelectorAll('.nav-tab');
        const contents = document.querySelectorAll('.zb-tab-content');

        function switchTab(hash) {
            const id = hash || '#manual-mode';
            tabs.forEach(tab => {
                tab.classList.toggle('nav-tab-active', tab.getAttribute('href') === id);
            });
            contents.forEach(content => {
                content.style.display = ('#' + content.id === id) ? 'block' : 'none';
            });
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const hash = this.getAttribute('href');
                window.location.hash = hash;
                switchTab(hash);
            });
        });

        // Initialisation au chargement (pour les liens directs ou retours page)
        if (window.location.hash) {
            switchTab(window.location.hash);
        } else {
            switchTab('#manual-mode');
        }

        // --- PARTIE 2 : CONFIGURATION DYNAMIQUE DES DOCUMENTS ---
        const fileInput = document.getElementById('zb_multi_file_input');
        const container = document.getElementById('zb_files_container');
        const metaList = document.getElementById('zb_file_meta_list');
        const submitBtn = document.getElementById('zb_submit_ingestion');
        const countDisplay = document.getElementById('file_count_display');

        const classesOptions = `<?php
                                $classes = get_terms(['taxonomy' => 'classe', 'hide_empty' => false]);
                                foreach ($classes as $c) echo '<option value="' . $c->term_id . '">' . esc_js($c->name) . '</option>';
                                ?>`;

        const matieresOptions = `<?php
                                    $matieres = get_terms(['taxonomy' => 'matiere', 'hide_empty' => false]);
                                    foreach ($matieres as $m) echo '<option value="' . $m->term_id . '">' . esc_js($m->name) . '</option>';
                                    ?>`;

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                container.innerHTML = '';
                const files = Array.from(this.files);

                if (files.length > 0) {
                    metaList.style.display = 'block';
                    submitBtn.style.display = 'block';
                    countDisplay.innerText = files.length + " fichier(s) prêt(s) à l'envoi";

                    files.forEach((file, index) => {
                        const fileNameClean = file.name.replace(/\.[^/.]+$/, "");
                        const row = document.createElement('div');
                        row.className = 'zb-file-row';

                        // Style de ligne Flexbox (alignement horizontal total)
                        row.style = "display: flex; align-items: center; justify-content:建设-between; gap: 15px; background: #fff; padding: 12px 20px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 10px; width: 100%; box-sizing: border-box;";

                        row.innerHTML = `
        <div style="flex: 2; min-width: 200px; font-weight: bold; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            <span class="dashicons dashicons-pdf" style="color:#ef4444; margin-right:5px; vertical-align: middle;"></span> ${file.name}
        </div>

        <div style="flex: 1.5;">
            <input type="text" name="file_origins[]" value="${fileNameClean}" placeholder="Source" style="width: 100%; margin: 0; height: 32px;">
        </div>

        <div style="flex: 1;">
            <select name="file_types[]" style="width: 100%; margin: 0; height: 32px;">
                <option value="Bac">Baccalauréat</option>
                <option value="Devoir">Devoir de classe</option>
            </select>
        </div>

        <div style="flex: 1;">
            <select name="file_classe_ids[]" style="width: 100%; margin: 0; height: 32px;" required>
                <option value="">-- Classe --</option>
                ${classesOptions}
            </select>
        </div>

        <div style="flex: 1;">
            <select name="file_matiere_ids[]" style="width: 100%; margin: 0; height: 32px;" required>
                <option value="">-- Matière --</option>
                ${matieresOptions}
            </select>
        </div>

        <div style="flex: 0 0 60px; text-align: right;">
             <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; border: 1px solid #166534;">PRÊT</span>
        </div>
    `;
                        container.appendChild(row);
                    });
                }
            });
        }
    });
</script>