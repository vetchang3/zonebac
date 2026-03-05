<?php
if (!defined('ABSPATH')) exit;

// RÉCUPÉRATION DE L'EXERCICE EN MODE ÉDITION
// Cette variable est passée par le contrôleur via set_query_var
$editing_exercise = get_query_var('zb_editing_exercise');
$is_edit = !empty($editing_exercise);

// Extraction des données JSON si on est en mode édition [cite: 2026-02-23]
$ex_data = $is_edit ? json_decode($editing_exercise->exercise_data, true) : null;
?>

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

    /* FORMULAIRE ÉDITION */
    .zb-edit-alert {
        background: #fffbeb;
        border-left: 4px solid #f59e0b;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
    }

    .zb-form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }

    .zb-form-group {
        flex: 1;
        min-width: 140px;
        margin-bottom: 15px;
    }

    .zb-form-group label {
        display: block;
        margin-bottom: 5px;
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
    }

    .zb-form-group select,
    .zb-form-group input,
    .zb-form-group textarea {
        width: 100%;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        padding: 8px;
    }

    .zb-btn-submit {
        height: 40px !important;
        background: #163A5E !important;
        border-color: #163A5E !important;
        padding: 0 25px !important;
        color: white !important;
        font-weight: bold !important;
        cursor: pointer;
    }

    /* Toggle Switch */
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
    }

    input:checked+.zb-slider:before {
        transform: translateX(24px);
    }
</style>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-welcome-write-blog"></span>
        <?php echo $is_edit ? "Modifier l'exercice" : "Générateur d'Exercices"; ?>
    </h1>
    <hr class="wp-header-end">

    <?php if ($is_edit) : ?>
        <div class="zb-edit-alert">
            <strong><span class="dashicons dashicons-warning"></span> Mode Édition Actif :</strong>
            Vous modifiez l'exercice : <em><?php echo esc_html($editing_exercise->title); ?></em>.
            <a href="?page=zonebac-ex-gen" style="margin-left:10px;">Annuler et créer un nouveau</a>
        </div>
    <?php endif; ?>

    <?php
    $message_code = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
    if ($message_code === 'success') : ?>
        <div class="notice updated is-dismissible">
            <p>Action effectuée avec succès !</p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <a href="#manual-mode" class="nav-tab <?php echo $is_edit ? 'nav-tab-active' : ''; ?>">Génération Manuelle</a>
        <a href="#smart-mode" class="nav-tab">Génération Intelligente & Planning</a>
        <a href="#ingestion-mode" class="nav-tab">Ingestion d'Archives (PDF/Doc)</a>
    </h2>

    <div id="manual-mode" class="zb-tab-content <?php echo $is_edit ? 'active' : ''; ?>">
        <div class="card" style="max-width: 100%; padding: 25px; border-radius: 12px;">
            <h3>Détails de l'exercice</h3>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="zb_do_exercise_generation">
                <?php wp_nonce_field('zb_ex_gen_action'); ?>

                <?php if ($is_edit): ?>
                    <input type="hidden" name="exercise_id" value="<?php echo intval($editing_exercise->id); ?>">
                <?php endif; ?>

                <div class="zb-form-group">
                    <label>Titre de l'exercice</label>
                    <input type="text" name="title" required value="<?php echo $is_edit ? esc_attr($editing_exercise->title) : ''; ?>" placeholder="Ex: Étude de la fonction exponentielle">
                </div>

                <div class="zb-form-group">
                    <label>Énoncé / Contexte (Sujet)</label>
                    <textarea name="subject_text" rows="8" required placeholder="Saisissez l'énoncé complet ici..."><?php echo $is_edit ? esc_textarea($editing_exercise->subject_text) : ''; ?></textarea>
                </div>

                <?php if (!$is_edit) : ?>
                    <?php $this->render_shared_hierarchy_form_fields(); ?>
                <?php endif; ?>

                <div style="margin-top: 20px;">
                    <button type="submit" class="zb-btn-submit">
                        <?php echo $is_edit ? "Mettre à jour l'exercice" : "Lancer la génération"; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="smart-mode" class="zb-tab-content">
        <div class="card zb-full-width" style="border-left: 4px solid #475569; background: #f1f5f9; padding: 20px; border-radius: 12px;">
            <h3 style="margin-top:0;"><span class="dashicons dashicons-info"></span> Guide d'utilisation stratégique</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <div><strong>1. Maillage Pédagogique</strong>
                    <p class="description">Crée les liens pour les exercices de synthèse.</p>
                </div>
                <div><strong>2. Planning & Seuil</strong>
                    <p class="description">Le dispatcher travaille quand le Mode Production est sur ON.</p>
                </div>
                <div><strong>3. Environnement</strong>
                    <p class="description">Utilisez le bouton Test pour forcer l'exécution.</p>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 20px; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
            <h4 style="margin:0;"><span class="dashicons dashicons-performance"></span> Contrôle du Moteur</h4>
            <?php
            $settings = Zonebac_Settings_Model::get_settings();
            $is_enabled = ($settings['enable_smart_dispatcher'] ?? 'no') === 'yes';
            ?>
            <form method="post" action="admin-post.php">
                <input type="hidden" name="action" value="zb_save_dispatcher_status">
                <?php wp_nonce_field('zb_dispatcher_status_nonce'); ?>
                <div class="zb-toggle-wrapper">
                    <label class="zb-switch">
                        <input type="checkbox" name="enable_smart_dispatcher" value="yes" <?php checked($is_enabled); ?>>
                        <span class="zb-slider"></span>
                    </label>
                    <strong><?php echo $is_enabled ? "Mode Production Actif" : "Mode Pause"; ?></strong>
                </div>
                <button type="submit" class="button button-primary">Enregistrer</button>
            </form>
        </div>

        <div class="zb-final-container">
            <div class="zb-col-70" style="flex:1;">
                <h3>État de Santé des Notions</h3>
            </div>
        </div>
    </div>

    <div id="ingestion-mode" class="zb-tab-content">
        <div class="card" style="border-left: 4px solid #8b5cf6; padding: 25px; border-radius: 12px;">
            <h3><span class="dashicons dashicons-upload"></span> Ingestion d'épreuves</h3>
            <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="zb_handle_file_ingestion">
                <?php wp_nonce_field('zb_ingestion_nonce'); ?>
                <input type="file" name="zb_archives[]" multiple accept=".pdf" class="button">
                <?php submit_button('Lancer l\'ingestion'); ?>
            </form>
        </div>
    </div>

    <div class="zb-jobs-monitor">
        <h3><span class="dashicons dashicons-list-view"></span> Suivi de la file d'attente (Jobs)</h3>
        <?php if (isset($ex_job_table)) $ex_job_table->display(); ?>
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
                const target = this.getAttribute('href');
                switchTab(target);
                history.pushState(null, null, target);
            });
        });

        // Gestion de l'ancre au chargement
        const hash = window.location.hash || (<?php echo $is_edit ? "'#manual-mode'" : "'#smart-mode'"; ?>);
        switchTab(hash);
    });
</script>