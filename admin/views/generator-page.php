<?php if (!defined('ABSPATH')) {
    exit;
} ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><span class="dashicons dashicons-hammer"></span> Générateur de Questions</h1>
    <hr class="wp-header-end">

    <?php if (!empty($message)) : ?>
        <div class="<?php echo esc_attr($message_type); ?> notice is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php settings_errors('zb_messages'); ?>
    <?php if (isset($_GET['scheduled'])) : ?>
        <div class="updated notice is-dismissible"><p>Le traitement a été lancé avec succès via DeepSeek en arrière-plan.</p></div>
    <?php endif; ?>

    <div class="card zb-generator-full-width">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="zb-grid-form">
            <input type="hidden" name="action" value="zb_do_generation">
            <?php wp_nonce_field('zb_gen_action'); ?>

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
                <select id="zb-matiere" name="matiere_id" disabled required><option value="">-- Attente --</option></select>
            </div>

            <div class="zb-field">
                <label for="zb-chapitre">Chapitre</label>
                <select id="zb-chapitre" name="chapitre_id" disabled required><option value="">-- Attente --</option></select>
            </div>

            <div class="zb-field">
                <label for="zb-notion">Notion</label>
                <select id="zb-notion" name="notion_id" disabled required><option value="">-- Attente --</option></select>
            </div>

            <div class="zb-field zb-field-number">
                <label for="nb_questions">Nombre</label>
                <input id="nb_questions" name="nb_questions" type="number" value="10" min="1" max="20">
            </div>

            <div class="zb-field zb-field-button">
                <label>&nbsp;</label>
                <?php submit_button('Lancer la génération', 'primary', 'submit', false, ['id' => 'submit-gen', 'disabled' => 'disabled']); ?>
            </div>
        </form>
    </div>

    <div id="poststuff" style="margin-top: 20px;">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php  $job_table->display(); ?>
        </form>
    </div>


</div>