<?php
if (!defined('ABSPATH')) exit;

class Zonebac_Smart_Engine
{

    /**
     * Analyse une matière et retourne les statistiques (STATIQUE)
     */
    public static function get_priority_notions($matiere_id, $threshold_n = 50)
    {
        global $wpdb;

        // Appel de la méthode statique de liaison
        $notions = self::get_all_notions_by_matiere($matiere_id);
        $priority_list = [];

        if (empty($notions)) return [];

        foreach ($notions as $notion) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}zb_exercises WHERE notion_id = %d",
                $notion->ID
            ));

            $gap = $threshold_n - $count;

            $priority_list[] = [
                'id'    => $notion->ID,
                'name'  => $notion->post_title,
                'count' => (int)$count,
                'gap'   => ($gap > 0) ? $gap : 0
            ];
        }

        usort($priority_list, function ($a, $b) {
            return $b['gap'] <=> $a['gap'];
        });

        return $priority_list;
    }

    /**
     * Dispatcher Automatique (STATIQUE)
     */
    public static function run_auto_dispatcher()
    {
        // Récupération des réglages
        $settings = Zonebac_Settings_Model::get_settings();

        // Si l'interrupteur n'est pas activé, on arrête tout immédiatement [cite: 2025-11-16]
        if (empty($settings['enable_smart_dispatcher']) || $settings['enable_smart_dispatcher'] !== 'yes') {
            // error_log("Zonebac: Dispatcher ignoré (Mode OFF)"); 
            return;
        }


        global $wpdb;

        $schedules = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}zb_smart_schedules");

        foreach ($schedules as $sched) {
            // APPEL DIRECT SANS "NEW"
            $priorities = self::get_priority_notions($sched->matiere_id, $sched->threshold_n);

            if (!empty($priorities)) {
                $top_priority = $priorities[0];
                if ($top_priority['gap'] > 0) {
                    $wpdb->insert($wpdb->prefix . 'zb_exercise_jobs', [
                        'notion_id'  => $top_priority['id'],
                        'count'      => 10,
                        'status'     => 'pending',
                        'created_at' => current_time('mysql')
                    ]);
                }
            }
        }
    }

    /**
     * Récupération des notions (STATIQUE)
     */
    private static function get_all_notions_by_matiere($matiere_id)
    {
        global $wpdb;

        // Utilisation de la clé validée par le diagnostic
        $chapitre_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'parent_id' AND meta_value = %d",
            $matiere_id
        ));

        if (empty($chapitre_ids)) return [];

        return get_posts([
            'post_type'      => 'notion',
            'posts_per_page' => -1,
            'tax_query'      => [[
                'taxonomy' => 'chapitre',
                'field'    => 'term_id',
                'terms'    => $chapitre_ids,
                'operator' => 'IN'
            ]],
            'orderby' => 'title',
            'order'   => 'ASC'
        ]);
    }

    public static function map_notions_relations_with_ai()
    {
        global $wpdb;

        // 1. Récupérer TOUTES les notions existantes pour servir de référentiel [cite: 2025-11-16]
        $all_notions = get_posts(['post_type' => 'notion', 'numberposts' => -1, 'post_status' => 'publish']);
        $ref_list = implode(', ', wp_list_pluck($all_notions, 'post_title'));

        // 2. Cibler uniquement les notions qui n'ont pas encore de liens (notre cache SQL)
        $table_rel = $wpdb->prefix . 'zb_notion_relations';
        $to_scan = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} 
        WHERE post_type = 'notion' AND post_status = 'publish' 
        AND ID NOT IN (SELECT notion_id FROM $table_rel) LIMIT 5");

        foreach ($to_scan as $notion) {
            $prompt = "Voici une notion cible : '{$notion->post_title}'. 
        Choisis EXCLUSIVEMENT dans cette liste de notions existantes les 2 plus complémentaires pour un exercice de synthèse :
        [$ref_list].
        Réponds au format JSON : {\"relations\": [{\"notion_suggeree\": \"NOM_EXACT\"}]}";

            $response = Zonebac_DeepSeek_API::call_deepseek_raw($prompt);
            if ($response) {
                $data = json_decode(preg_replace('/^```json|```$/m', '', $response), true);
                self::save_ai_relations($notion->ID, $data);
            }
        }
    }

    private static function save_ai_relations($notion_id, $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'zb_notion_relations';

        if (!isset($data['relations']) || !is_array($data['relations'])) {
            error_log("Zonebac Debug: Format JSON invalide reçu de l'IA.");
            return;
        }

        foreach ($data['relations'] as $rel) {
            $suggested_name = sanitize_text_field($rel['notion_suggeree']);

            // Recherche plus souple (LIKE) pour trouver la notion [cite: 2025-11-16]
            $related_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'notion' 
             AND post_status = 'publish'
             AND post_title LIKE %s 
             LIMIT 1",
                '%' . $wpdb->esc_like($suggested_name) . '%'
            ));

            if ($related_id && $related_id != $notion_id) {
                $wpdb->replace($table, [
                    'notion_id'         => $notion_id,
                    'related_notion_id' => $related_id,
                    'strength'          => 5
                ]);
                error_log("Zonebac Mapping: LIEN CRÉÉ entre " . get_the_title($notion_id) . " et " . get_the_title($related_id));
            } else {
                error_log("Zonebac Mapping: Notion suggérée '$suggested_name' non trouvée en base.");
            }
        }
    }


    public static function analyze_pdf_content_step_1($file_id)
    {
        $cache_key = 'zb_pdf_text_' . $file_id;
        $cached_content = get_transient($cache_key);
        if (false !== $cached_content) return $cached_content;

        $file_path = get_attached_file($file_id);
        if (!$file_path) {
            global $wpdb;
            $file_path = $wpdb->get_var($wpdb->prepare("SELECT file_path FROM {$wpdb->prefix}zb_file_ingestion WHERE id = %d", $file_id));
        }

        $prompt = "Extraire tout le texte brut au format Markdown avec LaTeX.";
        $result = Zonebac_DeepSeek_API::call_deepseek_vision($file_path, $prompt);

        // Basculement intelligent vers Gemini si DeepSeek détecte un scan ou échoue
        if (empty($result) || strpos($result, "image scannée") !== false || strpos($result, "Erreur") !== false) {
            error_log("ZB DEBUG: DeepSeek insuffisant, basculement vers Gemini Vision pour le fichier: " . $file_path);

            if (file_exists($file_path)) {
                // CRITIQUE : Lire le contenu réel et l'encoder en Base64
                $file_binary = file_get_contents($file_path);
                $base64_data = base64_encode($file_binary);

                $result = Zonebac_Gemini_API::call_gemini_vision($base64_data, $prompt);
            } else {
                $result = "Erreur : Fichier introuvable sur le serveur.";
            }
        }

        if ($result && strpos($result, 'Erreur') === false) {
            set_transient($cache_key, $result, DAY_IN_SECONDS);
        }
        return $result;
    }
    public static function identify_sections_and_notions($extracted_text)
    {
        $all_notions = get_posts(['post_type' => 'notion', 'numberposts' => -1, 'post_status' => 'publish']);
        $notions_ref = implode(', ', wp_list_pluck($all_notions, 'post_title'));

        $prompt = "Tu es un expert en épreuves de Baccalauréat. 
            Découpe l'épreuve suivante en utilisant EXACTEMENT ces balises pour séparer les exercices.
            
            Format attendu :
            [SECTION_START]
            TITLE: Titre de l'exercice
            NOTIONS: Nom de la Notion 1, Notion 2
            CONTENT: Texte intégral de l'exercice
            [SECTION_END]

            RÉFÉRENTIEL DES NOTIONS : [$notions_ref]

            TEXTE À ANALYSER :
            " . $extracted_text;

        $response = Zonebac_DeepSeek_API::call_deepseek_raw($prompt);

        // PARSING PHP ROBUSTE (Étape 3.2/3.3) [cite: 2025-11-16]
        $sections = [];
        // On cherche les blocs [SECTION_START] ... [SECTION_END] de manière insensible à la casse [cite: 2025-11-16]
        preg_match_all('/\[SECTION_START\](.*?)\[SECTION_END\]/si', $response, $matches);

        foreach ($matches[1] as $block) {
            // Extraction avec support multi-lignes et espaces [cite: 2025-11-16]
            preg_match('/TITLE:\s*(.*?)\n/i', $block, $title);
            preg_match('/NOTIONS:\s*(.*?)\n/i', $block, $notions);
            preg_match('/CONTENT:\s*(.*)/si', $block, $content);

            $sections[] = [
                'title'   => trim($title[1] ?? 'Exercice'),
                'notions' => array_map('trim', explode(',', $notions[1] ?? '')),
                'content' => trim($content[1] ?? '')
            ];
        }

        // Si aucune section n'est trouvée, on logue pour voir ce que l'IA a vraiment répondu [cite: 2025-11-16]
        if (empty($sections)) {
            error_log("Zonebac Debug: Aucune section trouvée dans la réponse IA: " . $response);
        }

        return json_encode(['sections' => $sections]);
    }
    /**
     * ÉTAPE 3.4 : FORMATAGE LATEX EXPERT (COMPLET)
     */
    public static function format_latex_content($text)
    {
        $prompt = "Tu es un expert en édition scientifique et typographie mathématique pour le Baccalauréat.
        Ton rôle est de transformer TOUTES les expressions mathématiques du texte fourni en format LaTeX strict en utilisant les délimiteurs $...$.

        CONSIGNES DE SÉCURITÉ :
        - Utilise impérativement le double antislash (\\\\) pour toutes les commandes LaTeX (ex: \\\\frac, \\\\sin, \\\\lim).
        - Encapsule chaque entité mathématique (variable seule, symbole ou formule complexe) entre $.

        RÉFÉRENTIEL DES FONCTIONS À COUVRIR :
        1. Standards : \\\\sin, \\\\cos, \\\\tan, \\\\arcsin, \\\\arccos, \\\\arctan, \\\\sinh, \\\\cosh, \\\\tanh.
        2. Log/Exp : \\\\ln, \\\\log, \\\\exp.
        3. Opérateurs : \\\\lim, \\\\lim_{x \\\\to \\\\infty}, \\\\max, \\\\min, \\\\sup, \\\\inf, \\\\det, \\\\dim.
        4. Calcul : \\\\sqrt{x}, \\\\sqrt[n]{x}, \\\\frac{a}{b}, \\\\binom{n}{k}.
        5. Sommes/Intégrales : \\\\sum, \\\\prod, \\\\int, \\\\iint, \\\\oint (avec bornes si présentes).
        6. Vecteurs/Styles : \\\\vec{u}, \\\\overrightarrow{AB}, \\\\mathbb{R}, \\\\mathcal{C}.
        7. Accents : \\\\hat, \\\\bar, \\\\tilde, \\\\overline.
        8. Parenthèses dynamiques : \\\\left( ... \\\\right), \\\\left[ ... \\\\right].
        9. Matrices : Utilise les environnements \\\\begin{pmatrix} ... \\\\end{pmatrix}.

        CONTRAINTES DE SORTIE :
        - Ne modifie pas le texte littéraire (le français).
        - Ne réponds QUE par le texte formaté, sans aucune introduction ni conclusion.

        Texte à traiter :
        " . $text;

        return Zonebac_DeepSeek_API::call_deepseek_raw($prompt);
    }

    public static function identify_sections_only($extracted_text, $file_id = 0)
    {
        if (empty($extracted_text)) return [];

        $prompt = "Tu es un expert en édition pédagogique. Ta mission est de découper l'épreuve en sections et de NETTOYER le contenu.
        CONSIGNES DE NETTOYAGE (CRITIQUE) :
        1. SUPPRESSION : Élimine systématiquement les numéros de page (ex: 1/3, 2/3).
        2. FILTRAGE : Supprime les mentions publicitaires, les noms de sites web (ex: 'Fomesoutra.com', 'ça soutra !'), et les en-têtes d'école ou de lycée qui se répètent.
        3. TEXTE PUR : Ne garde que l'énoncé de l'exercice, les questions et les données mathématiques/scientifiques.
        4. LATEX : Préserve absolument toutes les formules LaTeX intactes.

        TEXTE BRUT :
        $extracted_text
        
        FORMAT JSON STRICT :
        {
        \"sections\": [
            { \"title\": \"Exercice X\", \"content\": \"Contenu nettoyé sans fioritures...\" }
        ]
        }";

        $response = Zonebac_DeepSeek_API::call_deepseek_raw($prompt, true);

        // Ton code de nettoyage par substr que nous avons ajouté précédemment
        $start_pos = strpos($response, '{');
        $end_pos = strrpos($response, '}');
        if ($start_pos !== false && $end_pos !== false) {
            $response = substr($response, $start_pos, ($end_pos - $start_pos) + 1);
        }

        $data = json_decode($response, true);
        $sections = $data['sections'] ?? [];

        // 2. Persistance en BD si un file_id est fourni [cite: 2025-11-16]
        if ($file_id > 0 && !empty($sections)) {
            global $wpdb;
            $table = $wpdb->prefix . 'zb_pdf_sections';

            // On nettoie les anciennes extractions pour ce fichier avant de réécrire
            $wpdb->delete($table, ['file_id' => $file_id]);

            foreach ($sections as $sec) {
                $wpdb->insert($table, [
                    'file_id'       => $file_id,
                    'section_title' => sanitize_text_field($sec['title']),
                    'raw_content'   => wp_unslash($sec['content']), // Garde le LaTeX pur
                    'status'        => 'extracted'
                ]);
            }
        }

        return $sections;
    }

    public static function match_notions_to_sections($sections_html)
    {
        if (empty($sections_html) || strpos($sections_html, 'Erreur') !== false) return $sections_html;

        $all_notions = get_posts(['post_type' => 'notion', 'numberposts' => -1, 'post_status' => 'publish']);
        $notions_list = implode(', ', wp_list_pluck($all_notions, 'post_title'));

        $prompt = "Tu es un expert pédagogique. Voici des exercices de mathématiques issus d'un scan.
        Associe chaque exercice à la notion la plus pertinente de cette liste : [$notions_list].
        
        CONSIGNES DE SORTIE :
        - Réponds UNIQUEMENT par un tableau HTML <table> stylisé avec des classes WordPress (widefat striped).
        - Ne mets AUCUNE balise Markdown comme ```html.
        - Si une notion n'est pas dans la liste, suggère la plus proche.
        
        CONTENU À ANALYSER :
        " . strip_tags($sections_html); // On nettoie le HTML pour ne pas saturer le prompt

        $response = Zonebac_DeepSeek_API::call_deepseek_raw($prompt, false);

        // Nettoyage de sécurité au cas où l'IA n'aurait pas obéi
        return preg_replace('/^```html|```$/m', '', $response);
    }

    /**
     * ÉTAPE 4 : GÉNÉRATION DES MÉTA-DONNÉES PÉDAGOGIQUES
     * Analyse une section et produit un JSON riche incluant le résumé et les questions transformées.
     */
    public static function generate_question_metadata($section_text, $notion_name, $doc_type = 'Bac')
    {
        $prompt = "Tu es un concepteur pédagogique expert pour le Baccalauréat. 
            Analyse l'exercice suivant (Type : $doc_type) portant sur la notion : '$notion_name'.
            
            MISSIONS CRITIQUES :
            1. JSON STRICT : Ta réponse doit être un JSON pur et valide. Ne mets aucun texte avant ou après.
            2. RÉSUMÉ : Rédige un court paragraphe (2 lignes max) sur l'objectif pédagogique.
            3. QUESTIONS : Pour chaque question, crée un item de quiz. Transforme les questions ouvertes en questions de validation.
            4. AUCUN CHAMP VIDE : Ne laisse jamais les champs 'options' ou 'explanation' vides. Si une question est une démonstration, crée 4 options représentant des étapes logiques ou des résultats possibles.
            5. ÉCHAPPEMENT LATEX : Utilise impérativement le format $...$ (inline) et $$...$$ (bloc). 
            IMPORTANT : Utilise le double antislash (\\\\) pour TOUTES les commandes LaTeX (ex: \\\\frac, \\\\ln, \\\\mathbb) pour ne pas corrompre le JSON.

            TEXTE DE L'EXERCICE :
            $section_text

            FORMAT JSON ATTENDU :
            {
                \"summary\": \"Objectif pédagogique ici...\",
                \"questions\": [
                    {
                        \"question\": \"Énoncé précis avec LaTeX\",
                        \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
                        \"answer\": \"La bonne réponse exacte (doit figurer dans les options)\",
                        \"explanation\": \"Démonstration pédagogique complète avec LaTeX\",
                        \"difficulty\": \"Moyen\",
                        \"points\": 3
                    }
                ]
            }";

        // On force l'IA à répondre avec un objet JSON
        $response = Zonebac_DeepSeek_API::call_deepseek_raw($prompt, true);

        if (!$response) return null;

        // Nettoyage rigoureux des balises markdown si présentes
        $json_clean = preg_replace('/^```json|```$/m', '', $response);
        $data = json_decode(trim($json_clean), true);

        // Debug : si le JSON est invalide, on logue l'erreur pour analyse
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ZB DEBUG: Erreur JSON JSON_ERROR_" . json_last_error() . " sur le fichier ID.");
            return null;
        }

        return $data;
    }

    /**
     * Flux : Identification des sections -> Composition de nouveaux exercices
     */
    public static function process_full_pedagogy($sections_array, $doc_type = 'Bac')
    {
        $final_data = [];
        $api = new Zonebac_DeepSeek_API();

        foreach ($sections_array as $index => $content) {
            // 1. On demande à l'IA d'identifier la notion (Plus simple et plus fiable)
            $prompt_notion = "Quelle est la notion mathématique principale de ce texte ? Réponds uniquement par son nom. Texte : " . substr($content, 0, 500);
            $notion_found = Zonebac_DeepSeek_API::call_deepseek_raw($prompt_notion, false);

            // 2. Paramètres de composition
            $params = [
                'pdf_source_content'    => $content,
                'count'                 => 10,
                'notion'                => trim($notion_found),
                'classe'                => 'Terminale S',
                'matiere'               => 'Mathématiques',
                'chapitre'              => 'Limites en un point',
                'notion'                => trim($notion_found),
                'f'                     => 30,
                'm'                     => 40,
                'd'                     => 30
            ];

            // 3. Composition du nouvel exercice inspiré
            $generated_json = $api->generate_exercise_batch($params);
            $metadata = json_decode($generated_json, true);

            if ($metadata) {
                $final_data[] = [
                    'id'       => $index + 1,
                    'notion'   => trim($notion_found),
                    'type'     => $doc_type,
                    'raw_text' => $content, // On garde l'original pour la mémoire
                    'metadata' => $metadata // Contient l'exercice composé (Titre, Sujet, 10 questions)
                ];
            }
        }

        return $final_data;
    }

    public static function compose_inspired_exercise($section_data)
    {
        $api = new Zonebac_DeepSeek_API();

        // 15 questions pour un "Problème", 10 sinon [cite: 2026-03-21]
        $is_probleme = (stripos($section_data['title'], 'Problème') !== false);
        $count = $is_probleme ? 15 : 10;

        $params = [
            'pdf_source_content' => $section_data['content'],
            'count'    => $count,
            'classe'   => 'Terminale',
            'matiere'  => 'Mathématiques',
            'spirit'   => 'Baccalauréat / Devoir de classe',
        ];

        $generated_json = $api->generate_exercise_batch($params);
        $data = json_decode($generated_json, true);

        if ($data && isset($data['questions'])) {
            $total_score = 0;
            $difficulty_counts = ['Facile' => 0, 'Moyen' => 0, 'Difficile' => 0];

            foreach ($data['questions'] as &$q) {
                $diff = ucfirst(strtolower($q['difficulty'] ?? 'Moyen'));
                $difficulty_counts[$diff]++;

                // Barème expert : Facile=1, Moyen=2, Difficile=3 
                if ($diff === 'Facile') $q['points'] = 1;
                elseif ($diff === 'Difficile') $q['points'] = 3;
                else $q['points'] = 2;

                $total_score += $q['points'];
            }

            // Détermination de la difficulté globale (la majorité l'emporte)
            arsort($difficulty_counts);
            $data['global_difficulty'] = key($difficulty_counts);
            $data['total_calculated_points'] = $total_score;
        }

        return $data;
    }
}
