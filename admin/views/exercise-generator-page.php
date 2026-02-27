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