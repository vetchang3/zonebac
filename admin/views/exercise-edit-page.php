<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>📝 Éditer l'Exercice : <?php echo esc_html($exercise->title); ?></h1>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="zb_save_exercise_edit">
        <input type="hidden" name="exercise_id" value="<?php echo $exercise->id; ?>">
        <?php wp_nonce_field('zb_edit_ex_nonce'); ?>

        <div class="card" style="max-width: 100%; margin-top: 20px; border-left: 4px solid #0ea5e9;">
            <h2>Sujet / Scénario de l'exercice</h2>
            <textarea name="subject_text" rows="5" style="width:100%; font-size:1.1em;"><?php echo esc_textarea($exercise->subject_text); ?></textarea>
        </div>

        <?php foreach ($questions as $i => $q) : ?>
            <div class="card" style="margin-top: 20px;">
                <h3>Question <?php echo ($i + 1); ?> (<?php echo ucfirst($q['type']); ?>)</h3>
                <input type="hidden" name="questions[<?php echo $i; ?>][type]" value="<?php echo $q['type']; ?>">

                <p><strong>Énoncé :</strong><br>
                    <textarea name="questions[<?php echo $i; ?>][question]" rows="2" style="width:100%"><?php echo esc_textarea($q['question']); ?></textarea>
                </p>

                <p><strong>Options (Cochez la ou les bonnes réponses) :</strong></p>
                <?php
                foreach ($q['options'] as $o_idx => $opt) :
                    // On détermine si l'option est marquée comme bonne
                    // Supporte à la fois un texte simple ou un tableau de réponses
                    $is_checked = is_array($q['answer']) ? in_array($opt, $q['answer']) : ($opt === $q['answer']);
                    $input_type = ($q['type'] === 'multi') ? 'checkbox' : 'radio';
                    $input_name = ($q['type'] === 'multi') ? "questions[$i][correct_indexes][]" : "questions[$i][correct_index]";
                ?>
                    <div style="margin-bottom: 5px;">
                        <input type="<?php echo $input_type; ?>" name="<?php echo $input_name; ?>" value="<?php echo $o_idx; ?>" <?php checked($is_checked); ?>>
                        <input type="text" name="questions[<?php echo $i; ?>][options][]" value="<?php echo esc_attr($opt); ?>" style="width:80%">
                    </div>
                <?php endforeach; ?>

                <p><strong>Explication :</strong><br>
                    <textarea name="questions[<?php echo $i; ?>][explanation]" rows="2" style="width:100%"><?php echo esc_textarea($q['explanation']); ?></textarea>
                </p>
            </div>
        <?php endforeach; ?>

        <div style="margin-top: 20px;">
            <?php submit_button('Sauvegarder les modifications', 'primary', 'submit', true, ['style' => 'width:100%; padding:10px; font-size:1.2em;']); ?>
        </div>
    </form>
</div>