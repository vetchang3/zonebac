<?php

class Zonebac_Import_Controller
{
    public function __construct()
    {
        add_action('admin_post_zb_import_programme', [$this, 'handle_import']);
    }

    public function handle_import()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Accès refusé.'));
        }
        check_admin_referer('zb_import_verify');

        if (!isset($_FILES['programme_json']) || $_FILES['programme_json']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('admin.php?page=zonebac-lms&import=error'));
            exit;
        }

        $json_content = file_get_contents($_FILES['programme_json']['tmp_name']);
        $data = json_decode($json_content, true);

        if ($data) {
            $this->process_import_data($data);
            wp_redirect(admin_url('admin.php?page=zonebac-lms&import=success'));
        } else {
            wp_redirect(admin_url('admin.php?page=zonebac-lms&import=format_error'));
        }
        exit;
    }

    private function process_import_data($data) {
        $classe_name = $data['classe'];
        $matiere_name = $data['matiere'];
    
        // 1. Classe
        $classe_obj = term_exists($classe_name, 'classe') ?: wp_insert_term($classe_name, 'classe');
        $classe_id  = is_array($classe_obj) ? $classe_obj['term_id'] : $classe_obj;
    
        // 2. Matière + Lien vers Classe
        $matiere_obj = term_exists($matiere_name, 'matiere') ?: wp_insert_term($matiere_name, 'matiere');
        $matiere_id  = is_array($matiere_obj) ? $matiere_obj['term_id'] : $matiere_obj;
        update_term_meta($matiere_id, 'parent_id', $classe_id); // Indispensable pour la cascade 
    
        foreach ($data['programmes'] as $item) {
            $chapitre_name = $item['chapitre'];
            
            // 3. Chapitre + Lien vers Matière
            $chapitre_obj = term_exists($chapitre_name, 'chapitre') ?: wp_insert_term($chapitre_name, 'chapitre');
            $chapitre_id  = is_array($chapitre_obj) ? $chapitre_obj['term_id'] : $chapitre_obj;
            update_term_meta($chapitre_id, 'parent_id', $matiere_id); // Indispensable pour la cascade 
    
            foreach ($item['notions'] as $notion_title) {
                // 4. Création de la Notion liée 
                $post_id = wp_insert_post([
                    'post_title'  => $notion_title,
                    'post_type'   => 'notion',
                    'post_status' => 'publish'
                ]);
    
                wp_set_object_terms($post_id, [(int)$classe_id], 'classe');
                wp_set_object_terms($post_id, [(int)$matiere_id], 'matiere');
                wp_set_object_terms($post_id, [(int)$chapitre_id], 'chapitre');
            }
        }
    }
}
