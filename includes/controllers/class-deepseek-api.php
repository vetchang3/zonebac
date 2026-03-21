<?php

class Zonebac_DeepSeek_API
{
    private $api_url = 'https://api.deepseek.com/v1/chat/completions';

    /**
     * Génère un lot de questions classiques (Usage standard)
     */
    public function generate_questions_batch($params)
    {
        $api_key = Zonebac_Settings_Model::get_api_key();
        if (empty($api_key)) return false;

        $prompt = $this->build_expert_prompt($params);

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => "Tu es un concepteur d'examens expert pour le programme ZoneBac."],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7
            ]),
            'timeout' => 120,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) return false;

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function build_expert_prompt($p)
    {
        // Sécurité : Valeurs par défaut si les clés sont absentes
        $p['f'] = $p['f'] ?? 30;
        $p['m'] = $p['m'] ?? 40;
        $p['d'] = $p['d'] ?? 30;
        $p['count'] = $p['count'] ?? 10;

        // Utilisation de sprintf pour une injection propre des variables
        // %s = string, %d = integer
        $template = "Tu es un concepteur d'examens expert pour le programme ZoneBac. 
        Génère %d questions de type %s pour :
        Niveau: %s
        Matière: %s
        Chapitre: %s
        Notion: %s

        CONSIGNES DE DIFFICULTÉ :
        Respecte rigoureusement ce ratio pour ce lot de questions :
        - Facile: %d%% (Définitions, rappels directs)
        - Moyen: %d%% (Calculs simples, application de cours)
        - Difficile: %d%% (Problèmes complexes, cas critiques du Bac)

        STRATÉGIE DE VARIÉTÉ OBLIGATOIRE :
        Pour chaque question, choisis un angle d'approche différent pour éviter toute redondance. Alterne parmi ces types :
        1. Compréhension Conceptuelle : Vérifier le 'pourquoi' (ex: 'Pourquoi cette loi s'applique-t-elle ici ?').
        2. Problème Scénarisé : Une mise en situation concrète ou un cas d'usage réel.
        3. Question de Validation : Présenter un raisonnement potentiellement faux et demander s'il est correct.
        4. Question Piège : Basée sur les erreurs classiques ou les confusions fréquentes des élèves.
        5. Analyse de Cas : À partir d'un court énoncé factuel, déduire une conséquence.
        6. Question 'Pour aller plus loin' : Lien avec une autre notion ou défi de niveau expert.
        7. Définition Technique : Précision du vocabulaire et des termes clés.

        CONSIGNES SCIENTIFIQUES & MÉDIAS :
        1.  Utilise impérativement le format LaTeX $...$ pour les formules, équations, symboles, variables, fonctions mathématiques et scientifiques (ex: $\f(x) = x^2$).
           IMPORTANT : Pour TOUTES les fonctions comme par exemple frac, sqrt, etc., écris TOUJOURS un double antislash devant (ex: \\\\frac{a}{b}) pour que le format JSON soit valide.

        2. IMAGES : Si la question nécessite un schéma ou un graphique, remplis 'image_suggestion' avec une description détaillée. Sinon, laisse vide.

        FORMAT JSON ATTENDU :
        {
            \"questions\": [
              {
                \"question\": \"Énoncé précis avec LaTeX si besoin\",
                \"options\": [\"A\", \"B\", \"C\", \"D\"],
                \"answer\": \"Texte exact de la bonne réponse\",
                \"explanation\": \"Explication pédagogique avec LaTeX\",
                \"difficulty\": \"Facile\" | \"Moyen\" | \"Difficile\",
                \"image_suggestion\": \"Description du visuel\"
              }
            ]
        }";

        return sprintf(
            $template,
            $p['count'],    // %d
            $p['type'],     // %s
            $p['classe'],   // %s
            $p['matiere'],  // %s
            $p['chapitre'], // %s
            $p['notion'],   // %s
            $p['f'],        // %d
            $p['m'],        // %d
            $p['d']         // %d
        );
    }

    public function generate_exercise_batch($params)
    {
        $api_key = Zonebac_Settings_Model::get_api_key();
        if (empty($api_key)) return false;

        // Appel du nouveau prompt de composition inspirée [cite: 2025-11-16]
        $prompt = $this->build_exercise_prompt($params);

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => "Tu es un concepteur d'examens expert pour le programme ZoneBac."],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7
            ]),
            'timeout' => 120,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) return false;

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }
    private function build_exercise_prompt($p)
    {
        // Sécurisation forcée des clés pour éviter le PHP Warning et l'échec du sprintf
        $p['classe']   = !empty($p['classe'])   ? $p['classe']   : 'Terminale';
        $p['matiere']  = !empty($p['matiere'])  ? $p['matiere']  : 'Général';
        $p['chapitre'] = !empty($p['chapitre']) ? $p['chapitre'] : 'Limites';
        $p['notion']   = !empty($p['notion'])   ? $p['notion']   : 'Limites en un point';
        $p['count']    = !empty($p['count'])    ? $p['count']    : 10;
        $p['f'] = isset($p['f']) ? $p['f'] : 30;
        $p['m'] = isset($p['m']) ? $p['m'] : 40;
        $p['d'] = isset($p['d']) ? $p['d'] : 30;

        $context_instruction = "";
        if (!empty($p['extra_notions'])) {
            $notions_list = implode(', ', $p['extra_notions']);
            $context_instruction = "IMPORTANT : Il s'agit d'un exercice de SYNTHÈSE. Tu DOIS obligatoirement créer des ponts logiques et progressifs avec les notions suivantes : $notions_list.";
        } elseif (!empty($p['pdf_source_content'])) {
            $context_instruction =  "IMPORTANT : Inspire toi de l'exercice suivant pour créer un nouvel execice SIMILAIRE :\n" . $p['pdf_source_content'];
        } else {
            $context_instruction = "Focus : Concentre l'exercice exclusivement sur l'exploration approfondie de la notion principale.";
        }


        $template = "Tu es un concepteur d'examens expert pour le programme du Baccalauréat. 
        Génère un EXERCICE complet pour :
        Niveau: %s | Matière: %s | Chapitre: %s | Notion: %s
        Nombre de questions : %d

        STRATÉGIE DE SCÉNARISATION :
        L'exercice doit impérativement commencer par un 'subject_text' : une mise en situation concrète ou un cas d'usage réel du baccalauréat. 
        Toutes les questions doivent être liées à ce sujet.
        $context_instruction

        TYPES DE QUESTIONS (Variété obligatoire) :
        1. Single Choice (4 options) : Une seule bonne réponse.
        2. Multi Choice (5 options) : Une seule bonne réponse mais format plus complexe.
        2. Pour le type 'multi choice' assure-toi qu'il y ait EXACTEMENT DEUX bonnes réponses parmi les 5 options.
        3. Il doit toujours avoir une seule question de type Multi Choice dans un exercice
        4. IMPORTANT : Pour chaque question de type 'multi', termine systématiquement l'énoncé par la mention : ' (Deux choix possibles)'
        
        DÉMARCHE PÉDAGOGIQUE :
        1. Question de Validation : Présenter un raisonnement potentiellement faux et demander s'il est correct.
        2. Question Piège : Basée sur les erreurs classiques ou les confusions fréquentes des élèves.
        3. Analyse de Cas : À partir d'un court énoncé factuel, déduire une conséquence.
        4. Question 'Pour aller plus loin' : Lien avec une autre notion ou défi de niveau expert.
        5. Définition Technique : Précision du vocabulaire et des termes clés.

        CONSIGNES DE DIFFICULTÉ (Ratio rigoureux) :
        - Facile: %d%% | Moyen: %d%% | Difficile: %d%%

        CONSIGNES SCIENTIFIQUES :
        - Utilise impérativement le format LaTeX $...$ pour les formules, équations, symboles, variables, fonctions mathématiques et scientifiques (ex: $\f(x) = x^2$).
        - Écris TOUJOURS un double antislash devant les fonctions (ex: \\\\frac{a}{b}) pour la validité JSON.
        - IMAGES : Si la question nécessite un schéma ou un graphique, remplis 'image_suggestion' avec une description détaillée. Sinon, laisse vide.


        FORMAT JSON ATTENDU :
        {
            \"exercise_title\": \"Titre accrocheur du devoir\",
            \"subject_text\": \"Énoncé long de mise en situation...\",
            \"questions\": [
              {
                \"type\": \"single\" | \"multi\",
                \"question\": \"Énoncé lié au sujet\",
                \"points\": 1 | 2 | 3, -- (1 pour Facile, 2 pour Moyen, 3 pour Difficile)
                \"options\": [\"A\", \"B\", \"C\", \"D\"], -- (5 options si multi, ajout option E)
                \"answer\": \"Texte exact\" | [\"Rép 1\", \"Rép 2\"] -- (1 réponse exacte si single, 2 si multi),
                \"explanation\": \"Explication pédagogique complète, preécise et détaillée avec LaTeX\",
                \"difficulty\": \"Facile\" | \"Moyen\" | \"Difficile\",
                \"image_suggestion\": \"Générer une image de description si besoin\"
              }
            ]
        }";

        $fomattedExercice =  sprintf(
            $template,
            $p['classe'],
            $p['matiere'],
            $p['chapitre'],
            $p['notion'],
            $p['count'],
            $p['f'],
            $p['m'],
            $p['d']
        );
        error_log("ZB DEBUG: build_exercise_prompt " . $fomattedExercice);

        return $fomattedExercice;
    }

    public static function call_deepseek_raw($prompt, $force_json = false)
    {
        $settings = Zonebac_Settings_Model::get_settings();
        $api_key  = $settings['deepseek_key'] ?? '';
        if (empty($api_key)) return false;

        $body_params = [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'system', 'content' => 'Tu es un expert en pédagogie.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3 // Plus bas pour plus de précision sur le découpage
        ];

        // On n'active le JSON que si demandé explicitement
        if ($force_json) {
            $body_params['response_format'] = ['type' => 'json_object'];
        }

        $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ],
            'timeout' => 120, // Important pour les textes longs
            'body'    => json_encode($body_params),
            'sslverify' => false
        ]);

        if (is_wp_error($response)) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? false;
    }

    public static function call_deepseek_vision($file_path, $prompt)
    {
        $settings = Zonebac_Settings_Model::get_settings();
        $api_key  = $settings['deepseek_key'] ?? '';

        // 1. Extraction du texte brute du PDF en PHP
        require_once(plugin_dir_path(__FILE__) . '../../vendor/autoload.php');
        $parser = new \Smalot\PdfParser\Parser();

        try {
            $pdf = $parser->parseFile($file_path);
            $extracted_text = $pdf->getText();

            // Sécurité : si le texte est trop court, c'est probablement un scan image
            if (strlen(trim($extracted_text)) < 50) {
                return "Erreur : Ce PDF semble être une image scannée sans couche de texte. L'IA ne peut pas le lire sans modèle de vision.";
            }
        } catch (\Exception $e) {
            return "Erreur lors de la lecture locale du PDF : " . $e->getMessage();
        }

        // 2. Envoi du texte brut au modèle deepseek-chat (le seul disponible)
        $payload = [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'system', 'content' => 'Tu es un expert en extraction de texte. Ton rôle est de nettoyer et structurer le texte brut issu d\'un PDF de Baccalauréat.'],
                ['role' => 'user', 'content' => $prompt . "\n\nVoici le texte extrait du document :\n" . $extracted_text]
            ],
            'temperature' => 0.1
        ];

        $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ],
            'timeout' => 90,
            'body'    => json_encode($payload),
            'sslverify' => false
        ]);

        if (is_wp_error($response)) return "Erreur WP : " . $response->get_error_message();

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['choices'][0]['message']['content'] ?? "Erreur de réponse : " . $body;
    }
}
