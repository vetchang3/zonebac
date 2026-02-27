<?php if (!defined('ABSPATH')) exit; ?>

<style>
    /* STRUCTURE PANORAMIQUE FIXÉE */
    .zb-final-container {
        width: calc(100% - 20px);
        margin: 20px 0;
        display: flex !important;
        gap: 25px;
        box-sizing: border-box;
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

    /* ZONE 70% AVEC SCROLLBAR */
    .zb-col-70 {
        flex: 1;
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        min-width: 0;
        border: 1px solid #e2e8f0;
        /* max-height: 800px; */
        max-height: 550px;
        /* Hauteur max avant le scroll */
        overflow-y: auto;
        /* Active la scrollbar verticale */
    }

    .zb-col-70 table.wp-list-table {
        width: 100% !important;
        table-layout: auto !important;
        border: none;
    }

    .zb-col-70 td strong {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
        max-width: 350px;
        color: #1e293b;
    }

    /* Fix pour l'entête du tableau qui reste visible au scroll */
    .zb-col-70 thead th {
        position: sticky;
        top: -25px;
        background: white;
        z-index: 10;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
    }

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
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><span class="dashicons dashicons-welcome-write-blog"></span> Générateur d'Exercices</h1>
    <hr class="wp-header-end">

    <?php if (!empty($message)) : ?>
        <div class="<?php echo esc_attr($message_type); ?> notice is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <a href="#manual-mode" class="nav-tab nav-tab-active">Génération Manuelle</a>
        <a href="#smart-mode" class="nav-tab">Génération Intelligente & Planning</a>
    </h2>

    <div id="smart-mode" class="zb-tab-content">
        <div class="card" style="border-left: 4px solid #0ea5e9; background: #f8fafc; padding: 20px; margin-bottom: 20px;">
            <form method="get" style="display: flex; align-items: center; gap: 20px;">
                <input type="hidden" name="page" value="zonebac-ex-gen">
                <input type="hidden" name="smart_view" value="1">
                <label><strong>Analyser la banque pour :</strong></label>
                <select name="view_matiere" onchange="this.form.submit()" style="min-width:250px;">
                    <option value="">-- Choisir une matière --</option>
                    <?php
                    $matieres = get_terms(['taxonomy' => 'matiere', 'hide_empty' => false]);
                    foreach ($matieres as $m) : ?>
                        <option value="<?php echo $m->term_id; ?>" <?php selected(isset($_GET['view_matiere']) && $_GET['view_matiere'] == $m->term_id); ?>>
                            <?php echo esc_html($m->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="card" style="margin-bottom: 20px; border-left: 4px solid #10b981; background: #f0fdf4; padding: 15px;">
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
                <h3 style="margin-top:0;"><span class="dashicons dashicons-calendar-alt"></span> Planning Automatique</h3>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="zb_save_smart_schedule">
                    <?php wp_nonce_field('zb_smart_sched_nonce'); ?>
                    <p><strong>Matière cible :</strong><br>
                        <select name="matiere_id" style="width:100%; margin-top:5px;" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($matieres as $m) : ?><option value="<?php echo $m->term_id; ?>"><?php echo $m->name; ?></option><?php endforeach; ?>
                        </select>
                    </p>
                    <p><strong>Seuil $n$ :</strong><br><input type="number" name="threshold_n" value="50" style="width:100%;"></p>
                    <p><strong>Fréquence :</strong><br>
                        <select name="frequency" style="width:100%;">
                            <option value="daily">Quotidien</option>
                            <option value="weekly" selected>Hebdomadaire</option>
                        </select>
                    </p>
                    <?php submit_button('Enregistrer le planning', 'primary', 'submit', true, ['style' => 'width:100%']); ?>
                </form>
            </div>

            <div class="zb-col-70">
                <h3 style="margin-top:0;"><span class="dashicons dashicons-chart-bar"></span> État de Santé des Notions</h3>
                <?php if (empty($stats_notions)) : ?>
                    <p class="description">Veuillez sélectionner une matière ci-dessus.</p>
                <?php else : ?>
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
                                        <div style="background:#f1f5f9; border-radius:10px; height:10px; width:100%; overflow:hidden;">
                                            <div style="background:<?php echo $color; ?>; width:<?php echo $percent; ?>%; height:100%; transition:0.5s;"></div>
                                        </div>
                                        <small><?php echo $stat['count']; ?> / 50</small>
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display:flex; align-items:center; justify-content:flex-end; gap:10px;">
                                            <span style="background:<?php echo $color; ?>10; color:<?php echo $color; ?>; padding:4px 8px; border-radius:6px; font-weight:800;"><?php echo $stat['gap']; ?></span>
                                            <?php if ($stat['gap'] > 0) : ?>
                                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                                    <input type="hidden" name="action" value="zb_smart_priority_gen"><input type="hidden" name="notion_id" value="<?php echo $stat['id']; ?>">
                                                    <?php wp_nonce_field('zb_priority_gen_nonce'); ?>
                                                    <button type="submit" class="button button-small" title="Générer maintenant"><span class="dashicons dashicons-bolt"></span></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="manual-mode" class="zb-tab-content active">
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
        switchTab(window.location.hash || '#manual-mode');
    });
</script>