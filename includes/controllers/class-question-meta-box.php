<?php
class Zonebac_Question_Meta_Box
{
    public function __construct()
    {
        // On enregistre l'affichage
        add_action('add_meta_boxes', [$this, 'add_zb_question_fields']);

        // IMPORTANT : On enregistre la sauvegarde
        add_action('save_post', [$this, 'save_zb_question_data'], 10, 1);
    }

    public function add_zb_question_fields()
    {
        // On récupère l'ID soit dans l'URL, soit dans la session (gérée par le contrôleur admin)
        $q_id = 0;
        if (isset($_GET['zb_question_id'])) {
            $q_id = intval($_GET['zb_question_id']);
        } elseif (isset($_SESSION['zb_last_question_id'])) {
            $q_id = intval($_SESSION['zb_last_question_id']);
        }

        // Si on a un ID, on affiche la boîte
        if ($q_id > 0) {
            add_meta_box(
                'zb_question_editor',
                '📝 ÉDITION ZONEBAC : Question #' . $q_id,
                [$this, 'render_meta_box_content'],
                'notion',
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box_content($post)
    {
        global $wpdb;
        $q_id = 0;
        if (isset($_GET['zb_question_id'])) {
            $q_id = intval($_GET['zb_question_id']);
        } elseif (isset($_SESSION['zb_last_question_id'])) {
            $q_id = intval($_SESSION['zb_last_question_id']);
        }

        if (!$q_id) return;

        $table = $wpdb->prefix . 'zb_questions';
        $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $q_id));

        if (!$question) return;

        $data = json_decode($question->question_data, true);

        // Champ caché pour l'ID et Nonce de sécurité
        wp_nonce_field('zb_save_meta_box', 'zb_meta_box_nonce');
        echo '<input type="hidden" name="zb_current_q_id" value="' . $q_id . '">';

?>
        <div style="padding:10px; background:#f0f9ff; border:1px solid #0ea5e9;">
            <p><strong>Énoncé :</strong><br>
                <textarea name="zb_edit_q_text" rows="3" style="width:100%"><?php echo esc_textarea($data['question'] ?? ''); ?></textarea>
            </p>

            <p><strong>Options :</strong></p>
            <?php if (isset($data['options'])) : foreach ($data['options'] as $i => $opt) : ?>
                    <div style="margin-bottom:5px;">
                        <input type="radio" name="zb_correct_answer_index" value="<?php echo $i; ?>" <?php checked($opt, ($data['answer'] ?? '')); ?>>
                        <input type="text" name="zb_options[]" value="<?php echo esc_attr($opt); ?>" style="width:85%">
                    </div>
            <?php endforeach;
            endif; ?>

            <p><strong>Explication :</strong><br>
                <textarea name="zb_edit_q_expl" rows="3" style="width:100%"><?php echo esc_textarea($data['explanation'] ?? ''); ?></textarea>
            </p>
        </div>
<?php
    }

    public function save_zb_question_data($post_id)
    {
        // 1. Vérifications de sécurité et de contexte
        if (!isset($_POST['zb_meta_box_nonce']) || !wp_verify_nonce($_POST['zb_meta_box_nonce'], 'zb_save_meta_box')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['zb_current_q_id'])) {
            return;
        }

        global $wpdb;
        $q_id = intval($_POST['zb_current_q_id']);
        $table = $wpdb->prefix . 'zb_questions';

        /** * 2. RÉCUPÉRATION DES DONNÉES AVEC wp_unslash()
         * WordPress ajoute des antislashes magiques (magic quotes) aux données $_POST.
         * Pour du LaTeX, on doit les retirer avant de sauvegarder.
         */
        $raw_question = wp_unslash($_POST['zb_edit_q_text']);
        $raw_expl     = wp_unslash($_POST['zb_edit_q_expl']);
        $raw_options  = array_map('wp_unslash', $_POST['zb_options']);

        // On utilise sanitize_textarea_field pour la sécurité, mais après le unslash
        $new_question_text = sanitize_textarea_field($raw_question);
        $new_explanation   = sanitize_textarea_field($raw_expl);
        $new_options       = array_map('sanitize_text_field', $raw_options);

        $correct_index = isset($_POST['zb_correct_answer_index']) ? intval($_POST['zb_correct_answer_index']) : 0;
        $new_answer    = $new_options[$correct_index] ?? '';

        // 3. Préparation du JSON
        $updated_data = [
            'question'    => $new_question_text,
            'options'     => $new_options,
            'answer'      => $new_answer,
            'explanation' => $new_explanation,
            'difficulty'  => 'Moyen'
        ];

        // 4. Mise à jour de la table personnalisée
        $wpdb->update(
            $table,
            [
                'question_data' => json_encode($updated_data, JSON_UNESCAPED_UNICODE),
            ],
            ['id' => $q_id],
            ['%s'],
            ['%d']
        );

        // 5. Nettoyage de la session
        if (isset($_SESSION['zb_last_question_id'])) {
            unset($_SESSION['zb_last_question_id']);
        }
    }
}
