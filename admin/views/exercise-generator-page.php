<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><span class="dashicons dashicons-welcome-write-blog"></span> Générateur d'Exercices</h1>
    <hr class="wp-header-end">

    <?php if (!empty($message)) : ?>
        <div class="<?php echo esc_attr($message_type); ?> notice is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="card zb-generator-full-width">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="zb-grid-form">
            <input type="hidden" name="action" value="zb_do_exercise_generation">
            <?php wp_nonce_field('zb_ex_gen_action'); ?>

            <div class="zb-field">
                <label for="zb-classe">Classe</label>
                <select id="zb-classe" name="classe_id" required>
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($classes as $c) : ?>
                        <option value="<?php echo esc_attr($c->term_id); ?>"><?php echo esc_html($c->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="zb-field">
                <label for="zb-matiere">Matière</label>
                <select id="zb-matiere" name="matiere_id" disabled required>
                    <option value="">-- Attente --</option>
                </select>
            </div>

            <div class="zb-field">
                <label for="zb-chapitre">Chapitre</label>
                <select id="zb-chapitre" name="chapitre_id" disabled required>
                    <option value="">-- Attente --</option>
                </select>
            </div>

            <div class="zb-field">
                <label for="zb-notion">Notion</label>
                <select id="zb-notion" name="notion_id" disabled required>
                    <option value="">-- Attente --</option>
                </select>
            </div>

            <div class="zb-field zb-field-number">
                <label for="nb_questions">Questions</label>
                <input id="nb_questions" name="nb_questions" type="number" value="10" min="5" max="15">
            </div>

            <div class="zb-field zb-field-button">
                <label>&nbsp;</label>
                <?php submit_button('Générer l\'exercice', 'primary', 'submit', false, ['id' => 'submit-gen', 'disabled' => 'disabled']); ?>
            </div>
        </form>
    </div>

    <div id="poststuff" style="margin-top: 20px;">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php
            // La méthode display() va maintenant afficher : 
            // Bulk Actions + Notre Bouton Nettoyer (via extra_tablenav) + Le tableau
            if (isset($job_table)) {
                $job_table->prepare_items();
                $job_table->display();
            }
            if (isset($ex_job_table)) {
                $ex_job_table->prepare_items();
                $ex_job_table->display();
            }
            ?>
        </form>
    </div>
</div>