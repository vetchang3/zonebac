<?php if (!defined('ABSPATH')) exit; ?>

<style>
    /* Style de la Modal d'aperçu */
    #zb-preview-modal {
        display: none;
        position: fixed;
        z-index: 99999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(15, 23, 42, 0.85);
        /* Fond ardoise sombre */
        backdrop-filter: blur(4px);
    }

    .zb-modal-content {
        background-color: #ffffff;
        margin: 2% auto;
        padding: 0;
        border-radius: 16px;
        width: 80%;
        max-width: 1000px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .zb-modal-header {
        padding: 20px 30px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .zb-close-modal {
        color: #64748b;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.2s;
    }

    .zb-close-modal:hover {
        color: #1e293b;
    }

    .zb-preview-body {
        padding: 30px;
    }

    /* Styles pour les badges dans la table */
    .badge-archive {
        background: #ede9fe;
        color: #5b21b6;
        padding: 3px 8px;
        border-radius: 5px;
        font-size: 11px;
        font-weight: 700;
    }

    .badge-ia {
        background: #dcfce7;
        color: #166534;
        padding: 3px 8px;
        border-radius: 5px;
        font-size: 11px;
        font-weight: 700;
    }

    /* Animation de chargement */
    .zb-loader {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #8b5cf6;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 50px auto;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
</style>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-database"></span> Banque d'Exercices
    </h1>
    <hr class="wp-header-end">

    <div class="card" style="max-width: 100%; margin-top: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <form method="post">
            <?php
            // Affichage de la table
            $bank_table->prepare_items();
            $bank_table->display();
            ?>
        </form>
    </div>
</div>

<div id="zb-preview-modal">
    <div class="zb-modal-content">
        <div class="zb-modal-header">
            <h2 style="margin:0; font-size: 1.25rem; color: #1e293b;">Aperçu de l'exercice</h2>
            <span class="zb-close-modal">&times;</span>
        </div>
        <div id="zb-preview-target" class="zb-preview-body">
            <div class="zb-loader"></div>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        // 1. Déclenchement de l'aperçu au clic sur le lien "Aperçu"
        $(document).on('click', '.zb-view-exercise', function(e) {
            e.preventDefault();
            const exerciseId = $(this).data('id');

            // Afficher la modal et le loader
            $('#zb-preview-modal').fadeIn(200);
            $('#zb-preview-target').html('<div class="zb-loader"></div>');

            // Appel AJAX vers le contrôleur
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zb_get_exercise_preview',
                    id: exerciseId,
                    nonce: '<?php echo wp_create_nonce("zb_preview_nonce"); ?>'
                },
                success: function(response) {
                    $('#zb-preview-target').html(response);
                    // On relance MathJax si présent pour rendre le LaTeX [cite: 2025-11-16]
                    if (window.MathJax) {
                        MathJax.typesetPromise();
                    }
                },
                error: function() {
                    $('#zb-preview-target').html('<p style="color:red;">Erreur lors du chargement de l\'aperçu.</p>');
                }
            });
        });

        // 2. Fermeture de la modal
        $('.zb-close-modal').on('click', function() {
            $('#zb-preview-modal').fadeOut(200);
        });

        $(window).on('click', function(event) {
            if (event.target == document.getElementById('zb-preview-modal')) {
                $('#zb-preview-modal').fadeOut(200);
            }
        });
    });
</script>