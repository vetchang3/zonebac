<?php
trait Zonebac_Generator_Form_Trait
{
    public function render_shared_hierarchy_form($action_name, $nonce_name, $button_label = "Lancer la génération")
    {
        // On récupère les classes pour le premier sélecteur
        $classes = get_terms(['taxonomy' => 'classe', 'hide_empty' => false]);
?>
        <div class="card zb-generator-full-width">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="zb-grid-form">
                <input type="hidden" name="action" value="<?php echo esc_attr($action_name); ?>">
                <?php wp_nonce_field($nonce_name); ?>

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
                    <label for="nb_questions">Nombre</label>
                    <input id="nb_questions" name="nb_questions" type="number" value="10" min="1" max="20">
                </div>

                <div class="zb-field zb-field-button">
                    <label>&nbsp;</label>
                    <?php submit_button($button_label, 'primary', 'submit', false, ['id' => 'submit-gen', 'disabled' => 'disabled']); ?>
                </div>
            </form>
        </div>
<?php
    }
}
