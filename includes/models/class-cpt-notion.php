<?php

class Zonebac_CPT_Notion
{
    public function __construct()
    {
        add_action('init', [$this, 'register_cpt_notion']);
        add_action('init', [$this, 'register_taxonomies']);
    }

    public function register_cpt_notion()
    {
        register_post_type('notion', [
            'labels' => ['name' => 'Notions', 'singular_name' => 'Notion'],
            'public' => true,
            'show_in_rest' => true, // Crucial pour React
            'supports' => ['title', 'editor', 'custom-fields'],
            'menu_icon' => 'dashicons-welcome-learn-more',
        ]);
    }

    public function register_taxonomies()
    {
        // Hiérarchie : Classe > Matière > Chapitre
        $taxonomies = [
            'classe'  => 'Classes',
            'matiere' => 'Matières',
            'chapitre' => 'Chapitres'
        ];

        foreach ($taxonomies as $slug => $name) {
            register_taxonomy($slug, ['notion'], [
                'hierarchical' => true,
                'labels' => ['name' => $name],
                'show_in_rest' => true,
                'show_admin_column' => true,
            ]);
        }
    }
}
