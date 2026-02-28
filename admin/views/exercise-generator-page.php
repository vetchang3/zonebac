<?php if (!defined('ABSPATH')) exit; ?>

<style>
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
        const tabs = document.querySelectorAll('.nav-tab');
        const contents = document.querySelectorAll('.zb-tab-content');

        function switchTab(targetId) {
            tabs.forEach(tab => tab.classList.remove('nav-tab-active'));
            contents.forEach(content => content.classList.remove('active'));
            const activeTab = document.querySelector(`[href="${targetId}"]`);
            const activeContent = document.querySelector(targetId);
            if (activeTab && activeContent) {
                activeTab.classList.add('nav-tab-active');
                activeContent.classList.add('active');
            }
        }
        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                switchTab(this.getAttribute('href'));
                history.pushState(null, null, this.getAttribute('href'));
            });
        });
        switchTab(window.location.hash || '#smart-mode');
    });
</script>