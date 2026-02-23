<div class="wrap">
    <h1>Paramétrage Zonebac LMS</h1>
    <hr>
    <?php if (isset($_GET['settings_updated'])) : ?>
        <div class="updated notice is-dismissible"><p>Configuration API enregistrée avec succès.</p></div>
    <?php endif; ?>

    <?php if (isset($_GET['import']) && $_GET['import'] === 'success') : ?>
        <div class="updated notice is-dismissible">
            <p>Le programme a été importé avec succès !</p>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 600px;">
        <h2><span class="dashicons dashicons-upload"></span> Importer un programme JSON</h2>
        <p>Chargez la hiérarchie <i>Classe > Matière > Chapitre > Notion</i> en un clic.</p>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="zb_import_programme">
            <?php wp_nonce_field('zb_import_verify'); ?>
            
            <p>
                <input type="file" name="programme_json" accept=".json" required>
            </p>
            
            <?php submit_button('Démarrer l\'importation'); ?>
        </form>
    </div>

    <div class="card" style="margin-top: 20px; max-width: 600px;">
        <h2><span class="dashicons dashicons-admin-network"></span> Configuration API</h2>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="zb_save_settings">
            <?php wp_nonce_field('zb_settings_verify'); ?>
            
            <p>
                <label>Clé API DeepSeek :</label><br>
                <input type="password" name="deepseek_key" value="<?php echo esc_attr(Zonebac_Settings_Model::get_api_key()); ?>" class="regular-text" required>
            </p>
            
            <?php submit_button('Enregistrer la configuration'); ?>
        </form>
    </div>
</div>