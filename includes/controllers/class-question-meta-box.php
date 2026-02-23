<?php

class Zonebac_Question_Meta_Box
{
    public function __construct()
    {
        // On ajoute la box uniquement si on est dans l'éditeur d'une Notion
        add_action('add_meta_boxes', [$this, 'add_zb_question_fields']);
        add_action('save_post', [$this, 'save_zb_question_data']);
    }

    public function add_zb_question_fields()
    {
        // On vérifie si l'ID de la question est présent dans l'URL (via l'action Modifier)
        if (isset($_GET['zb_question_id'])) {
            add_meta_box(
                'zb_question_editor',
                'Édition de la Question Générée',
                [$this, 'render_meta_box_content'],
                'notion', // Votre Custom Post Type
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box_content($post)
    {
        global $wpdb;
        $q_id = intval($_GET['zb_question_id']);
        $table = $wpdb->prefix . 'zb_questions';


        wp_die('===== render_meta_box_content ======');
        exit;

        $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $q_id));

        if (!$question) {
            echo "Erreur : Question introuvable.";
            return;
        }

        $data = json_decode($question->question_data, true);

        // Sécurité Nonce
        wp_nonce_field('zb_save_meta_box', 'zb_meta_box_nonce');

        echo '<input type="hidden" name="zb_current_q_id" value="' . $q_id . '">';

        // Champ Énoncé
        echo '<p><strong>Énoncé de la question :</strong></p>';
        echo '<textarea name="zb_edit_q_text" rows="4" style="width:100%">' . esc_textarea($data['question']) . '</textarea>';

        // Champ Explication
        echo '<p><strong>Explication pédagogique (LaTeX) :</strong></p>';
        echo '<textarea name="zb_edit_q_expl" rows="4" style="width:100%">' . esc_textarea($data['explanation']) . '</textarea>';

        // Points et Difficulté
        echo '<div style="display:flex; gap:20px; margin-top:15px;">';
        echo '<div><label><strong>Points :</strong></label><br><input type="number" name="zb_edit_q_points" value="' . intval($question->points) . '"></div>';
        echo '<div><label><strong>Difficulté :</strong></label><br>';
        echo '<select name="zb_edit_q_diff">';
        foreach (['Facile', 'Moyen', 'Difficile'] as $d) {
            $sel = ($question->difficulty == $d) ? 'selected' : '';
            echo "<option value='$d' $sel>$d</option>";
        }
        echo '</select></div></div>';
    }

    public function save_zb_question_data($post_id)
    {
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

        // Récupération du JSON actuel pour ne pas perdre les options (non encore éditées ici)
        $current_json = $wpdb->get_var($wpdb->prepare("SELECT question_data FROM $table WHERE id = %d", $q_id));
        $data = json_decode($current_json, true);

        // Mise à jour des valeurs éditées
        $data['question'] = $_POST['zb_edit_q_text'];
        $data['explanation'] = $_POST['zb_edit_q_expl'];

        $wpdb->update(
            $table,
            [
                'question_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'difficulty'    => sanitize_text_field($_POST['zb_edit_q_diff']),
                'points'        => intval($_POST['zb_edit_q_points'])
            ],
            ['id' => $q_id]
        );
    }
}
