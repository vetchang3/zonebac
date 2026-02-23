<?php
/**
 * Vue du Générateur de Questions - Zonebac LMS
 * Design horizontal fixe avec CSS Grid
 */
require_once plugin_dir_path(__FILE__) . '../../includes/controllers/class-job-list-table.php';

$job_table = new Zonebac_Job_List_Table();
$job_table->prepare_items();

$message = '';
$message_type = '';

// Traitement de la soumission
if (isset($_POST['action']) && $_POST['action'] === 'zb_do_generation') {
    check_admin_referer('zb_gen_action');
    $generator = new Zonebac_Question_Generator();
    if ($generator->handle_generation_request()) {
        $message = "Génération planifiée avec succès !";
        $message_type = "updated";
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><span class="dashicons dashicons-hammer"></span> Générateur de Questions</h1>
    <hr class="wp-header-end">

    <?php if ($message) : ?>
        <div class="<?php echo $message_type; ?> notice is-dismissible"><p><?php echo $message; ?></p></div>
    <?php endif; ?>

    <div class="card zb-generator-full-width">
        <form method="post" action="" class="zb-grid-form">
            <input type="hidden" name="action" value="zb_do_generation">
            <?php wp_nonce_field('zb_gen_action'); ?>

            <div class="zb-field">
                <label>Classe</label>
                <select id="zb-classe" name="classe_id" required>
                    <option value="">-- Sélectionner --</option>
                    <?php foreach (get_terms(['taxonomy' => 'classe', 'hide_empty' => false]) as $c) {
                        echo "<option value='{$c->term_id}'>{$c->name}</option>";
                    } ?>
                </select>
            </div>

            <div class="zb-field">
                <label>Matière</label>
                <select id="zb-matiere" name="matiere_id" disabled required><option value="">-- Attente --</option></select>
            </div>

            <div class="zb-field">
                <label>Chapitre</label>
                <select id="zb-chapitre" name="chapitre_id" disabled required><option value="">-- Attente --</option></select>
            </div>

            <div class="zb-field">
                <label>Notion</label>
                <select id="zb-notion" name="notion_id" disabled required><option value="">-- Attente --</option></select>
            </div>

            <div class="zb-field zb-field-number">
                <label>Nombre</label>
                <input name="nb_questions" type="number" value="10" min="1" max="20">
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
            <?php $job_table->display(); ?>
        </form>
    </div>
</div>

<style>
    /* Conteneur principal en pleine largeur */
    .zb-generator-full-width {
        margin: 20px 0 !important;
        padding: 25px !important;
        border-left: 4px solid #2271b1 !important;
        background: #fff !important;
        max-width: none !important;
    }

    /* Grille : 4 filtres égaux (1fr), petit champ nombre, et bouton dimensionné */
    .zb-grid-form {
        display: grid;
        grid-template-columns: repeat(4, 1fr) 80px 200px; 
        gap: 20px;
        align-items: flex-end;
    }

    .zb-field { display: flex; flex-direction: column; }
    .zb-field label { font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #1d2327; }
    
    /* Harmonisation des hauteurs et bordures */
    .zb-field select, .zb-field input { 
        width: 100%; 
        height: 35px; 
        border: 1px solid #8c8f94;
        border-radius: 4px; 
        background-color: #fff;
    }

    /* Bouton réduit et centré dans sa colonne */
    .zb-field-button #submit-gen {
        width: 100% !important;
        margin: 0 !important;
        height: 35px !important;
        padding: 0 !important;
        line-height: 1 !important;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selects = { 
        classe: document.getElementById('zb-classe'), 
        matiere: document.getElementById('zb-matiere'), 
        chapitre: document.getElementById('zb-chapitre'), 
        notion: document.getElementById('zb-notion') 
    };
    const btn = document.getElementById('submit-gen');

    async function updateSelect(type, parentId, nextSelect, following = []) {
        nextSelect.disabled = true;
        nextSelect.innerHTML = '<option>Chargement...</option>';
        following.forEach(s => { s.disabled = true; s.innerHTML = '<option value="">-- Attente --</option>'; });
        btn.disabled = true;

        try {
            const response = await fetch(`<?php echo get_rest_url(); ?>zonebac/v1/get-hierarchy/?type=${type}&parent_id=${parentId}`);
            const data = await response.json();
            
            nextSelect.innerHTML = '<option value="">-- Sélectionner --</option>';
            if (Array.isArray(data) && data.length > 0) {
                data.forEach(item => { nextSelect.innerHTML += `<option value="${item.id}">${item.name}</option>`; });
                nextSelect.disabled = false;
            }
        } catch (e) { console.error(e); }
    }

    selects.classe.addEventListener('change', () => updateSelect('matiere', selects.classe.value, selects.matiere, [selects.chapitre, selects.notion]));
    selects.matiere.addEventListener('change', () => updateSelect('chapitre', selects.matiere.value, selects.chapitre, [selects.notion]));
    selects.chapitre.addEventListener('change', () => updateSelect('notion', selects.chapitre.value, selects.notion));
    selects.notion.addEventListener('change', () => { btn.disabled = !selects.notion.value; });
});
</script>