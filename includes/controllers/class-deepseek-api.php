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
                    ['role' => 'system', 'content' => "Tu es un concepteur d'examens expert pour le programme ZoneBac destiné aux élèves des classes de Terminale préparant l'examen du baccalauréat."],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.85
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

        {$this->get_consignes_question()}
        RÈGLE D'OR : Ne commente pas tes erreurs dans l'explication. Donne uniquement le contenu pédagogique final.

        CONSIGNES DE L'EXPLICATION
        Structure par blocs : Utilise des doubles sauts de ligne \n\n pour séparer distinctement les étapes du raisonnement.
        Titres de sections : Encadre les titres de sections importantes avec des doubles astérisques (ex: **Analyse des limites** :).
        Listes à puces : Utilise des tirets - pour les énumérations afin de faciliter la lecture rapide.


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
                    [
                        'role' => 'system',
                        'content' => "Tu es un concepteur d'examens expert pour le programme ZoneBac destiné aux élèves des classes de Terminale préparant l'examen du baccalauréat."
                    ],
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
    private function build_exercise_prompt_old($p)
    {
        // 1. Extraction dynamique des paramètres (Priorité aux données réelles) [cite: 2025-11-16]
        $classe   = !empty($p['classe'])   ? $p['classe']   : 'Terminale';
        $matiere  = !empty($p['matiere'])  ? $p['matiere']  : 'Général';
        $chapitre = !empty($p['chapitre']) ? $p['chapitre'] : 'Non classé';
        $notion   = !empty($p['notion'])   ? $p['notion']   : 'Général';

        $count    = !empty($p['count'])    ? intval($p['count']) : 10;
        $f = isset($p['f']) ? intval($p['f']) : 30;
        $m = isset($p['m']) ? intval($p['m']) : 40;
        $d = isset($p['d']) ? intval($p['d']) : 30;


        // 2. Gestion du contexte (Extraction vs Génération) [cite: 2026-03-21]
        $context_instruction = "";
        if (!empty($p['pdf_source_summary'])) {
            $context_instruction = "### MISSION D'INSPIRATION CRÉATIVE :
            Voici un résumé d'un exercice existant : 
            '{$p['pdf_source_summary']}'
            
            CONSIGNES :
            1. Utilise les compétences citées pour créer un exercice TOTALEMENT NOUVEAU.
            2. Change les fonctions (ex: si c'est une suite \$u_{n+1}=au_n+b$, utilise une suite définie par une fonction \$f(n)$).
            3. Le scénario doit être différent de l'original mais de même difficulté.";
        } elseif (!empty($p['extra_notions'])) {
            $notions_list = implode(', ', $p['extra_notions']);
            $context_instruction = "IMPORTANT : Il s'agit d'un exercice de SYNTHÈSE. Tu DOIS obligatoirement créer des ponts logiques avec : $notions_list.";
        }


        $template = "Tu es un concepteur d'examens pour le Baccalauréat. 
        Génère un EXERCICE complet (JSON UNIQUEMENT) :
        Niveau: %s | Matière: %s | Chapitre: %s | Notion: %s
        Nombre total de questions : %d

        STRUCTURE :
        1. 'exercise_title' : Titre court et pro.
        2. 'subject_text' : Mise en situation réelle (Bac). Pas de retour à la ligne physique.
        3. 'questions' : Liste d'objets JSON. Les questions sont liées au 'subject_text'

        $context_instruction


        TYPES DE QUESTIONS :
        - Type 'single' : 4 options, 1 seule bonne réponse.
        - Type 'multi' : 5 options, EXACTEMENT 2 bonnes réponses. Ajoute '(Deux choix possibles)' à la fin de l'énoncé.
        - EXIGENCE : Il doit y avoir EXACTEMENT UNE question de type 'multi' dans tout l'exercice, les autres sont 'single'.

        CONSIGNES TECHNIQUES (CRITIQUES) :
        - NE FAIS JAMAIS de retour à la ligne réel dans une valeur. Utilise '\\n'.
        - LaTeX : Utilise $...$ pour les formules, équations, symboles, variables, fonctions mathématiques et scientifiques et double les antislashs (ex: \\\\frac{a}{b}).
        - IMAGES : Si la question nécessite un schéma ou un graphique, remplis 'image_suggestion' avec une description détaillée. Sinon, laisse vide.
        - Ratio difficulté : %d%% Facile, %d%% Moyen, %d%% Difficile.

        CONSIGNES DE MISE EN PAGE  DE l'EXPLICATION DE LA REPONSE:
        - L'Explication est une démonstration pédagogique structurée
        - Utilise des doubles retours à la ligne (\\n\\n) pour séparer les étapes de calcul.
        - Utilise des puces (•) pour les arguments logiques.
        - Encapsule TOUTES les formules et variables dans des délimiteurs LaTeX $...$.
        - Exemple de structure attendue : 
        '1. Analyse de l'énoncé : \\n On identifie que... \\n\\n 2. Calcul détaillé : \\n $\\\\frac{a}{b} = c$ \\n\\n 3. Conclusion : \\n Donc la réponse est...

        
        FORMAT JSON ATTENDU :
        {
            \"exercise_title\": \"...\",
            \"subject_text\": \"...\",
            \"questions\": [
              {
                \"type\": \"single\",
                \"question\": \"...\",
                \"points\": 1,
                \"options\": [\"A\", \"B\", \"C\", \"D\"],
                \"answer\": \"Réponse A\",
                \"explanation\": \"1. Analyse... \\n\\n 2. Calcul...\",
                \"difficulty\": \"Facile\"
              }
            ]
        }";

        return sprintf($template, $classe, $matiere, $chapitre, $notion, $count, $f, $m, $d);
    }

    private function get_consignes_question()
    {
        return "TYPES DE QUESTIONS :
            1. Single Choice (4 options) / Multi Choice (5 options, exactement 2 bonnes réponses).
            2. Pour chaque 'multi', termine l'énoncé par : ' (Deux choix possibles)'.
            
            FORMATAGE DE L'EXPLICATION (STYLE REACT/TAILWIND) :
            L'explication doit être modulaire et visuellement aérée :
            - Utilise **...** pour mettre en gras les concepts et résultats.
            - Sépare obligatoirement les sections par DEUX retours à la ligne (\\\\n\\\\n).
            - Utilise des listes à puces (•) pour détailler les étapes.";
    }

    private function build_exercise_prompt($p)
    {
        // Sécurisation forcée des clés pour éviter le PHP Warning et l'échec du sprintf
        $classe   = !empty($p['classe'])   ? $p['classe']   : 'Terminale';
        $matiere  = !empty($p['matiere'])  ? $p['matiere']  : 'Général';
        $chapitre = !empty($p['chapitre']) ? $p['chapitre'] : 'Limites';
        $notion   = !empty($p['notion'])   ? $p['notion']   : 'Limites en un point';
        $count    = !empty($p['count'])    ? $p['count']    : 10;
        $f = isset($p['f']) ? $p['f'] : 30;
        $m = isset($p['m']) ? $p['m'] : 40;
        $d = isset($p['d']) ? $p['d'] : 30;


        $context_instruction = "";
        if (!empty($p['extra_notions'])) {
            $notions_list = implode(', ', $p['extra_notions']);
            $context_instruction = "IMPORTANT : Il s'agit d'un exercice de SYNTHÈSE. Tu DOIS obligatoirement créer des ponts logiques et progressifs avec les notions suivantes : $notions_list.";
        } else {
            $context_instruction = "Focus : Concentre l'exercice exclusivement sur l'exploration approfondie de la notion principale.";
        }


        $template = "Tu es un concepteur d'examens expert pour le programme du Baccalauréat. 
        Génère un EXERCICE complet pour :
        Niveau: %s | Matière: %s | Chapitre: %s | Notion: %s
        Nombre de questions : %d

        STRATÉGIE DE SCÉNARISATION :
        L'exercice doit impérativement commencer par un 'subject_text' : un cas d'usage réel du baccalauréat. 
        Toutes les questions doivent être liées à ce sujet.
        $context_instruction

        CONSIGNES DE DIFFICULTÉ (Ratio rigoureux) :
        - Facile: %d%% | Moyen: %d%% | Difficile: %d%%

        CONSIGNES SCIENTIFIQUES :
        - Utilise impérativement le format LaTeX $...$ pour les formules, équations, symboles, variables, fonctions mathématiques et scientifiques (ex: $\f(x) = x^2$).
        - Écris TOUJOURS un double antislash devant les fonctions (ex: \\\\frac{a}{b}) pour la validité JSON.
        - IMAGES : Si la question nécessite un schéma ou un graphique, remplis 'image_suggestion' avec une description détaillée. Sinon, laisse vide.

        {$this->get_consignes_question()}
        RÈGLE D'OR : Ne commente pas tes erreurs dans l'explication. Donne uniquement le contenu pédagogique final.

        CONSIGNES DE L'EXPLICATION
        Structure par blocs : Utilise des doubles sauts de ligne \n\n pour séparer distinctement les étapes du raisonnement.
        Titres de sections : Encadre les titres de sections importantes avec des doubles astérisques (ex: **Analyse des limites** :).
        Listes à puces : Utilise des tirets - pour les énumérations afin de faciliter la lecture rapide.


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

        error_log(sprintf("ZB API DEBUG: Niveau=%s, Matiere=%s, Chapitre=%s, Notion=%s", $classe, $matiere, $chapitre, $notion));
        return sprintf($template, $classe, $matiere, $chapitre, $notion, $count, $f, $m, $d);
    }



    public static function call_deepseek_raw($prompt, $force_json = false)
    {
        $settings = Zonebac_Settings_Model::get_settings();
        $api_key  = $settings['deepseek_key'] ?? '';
        if (empty($api_key)) return false;

        $body_params = [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'system', 'content' => "Tu es un concepteur d'examens expert pour le programme ZoneBac (Baccalauréat)."],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3
        ];

        if ($force_json) $body_params['response_format'] = ['type' => 'json_object'];

        $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', [
            'headers' => ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'],
            'timeout' => 120,
            'body'    => json_encode($body_params),
            'sslverify' => false
        ]);

        if (is_wp_error($response)) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['usage'])) {
            Zonebac_Settings_Model::log_api_usage('deepseek', $body['usage']['prompt_tokens'], $body['usage']['completion_tokens']);
        }

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
                [
                    'role' => 'system',
                    'content' => 'Tu es un expert en extraction de texte. Ton rôle est de nettoyer et structurer le texte brut issu d\'un PDF de Baccalauréat.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt . "\n\nVoici le texte extrait du document :\n" . $extracted_text
                ]
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


    public function generate_inspired_batch($p)
    {
        $api_key = Zonebac_Settings_Model::get_api_key();
        if (empty($api_key)) return false;

        // C'est ici qu'on réintègre les consignes de qualité perdues
        $prompt = "Tu es un expert en pédagogie. 
        SUJET CIBLE : '{$p['subject_text']}'
        COMPÉTENCE À TESTER : '{$p['original_pdf']}'
        MISSION : Génère UNIQUEMENT la {$p['range']} pour ce sujet.
        
        CONSIGNES :
        1. TYPE : Choisis entre 'single' (4 options, 1 réponse) ou 'multi' (5 options, 2 réponses).
        2. LATEX : Utilise \$...\$ et double les antislashs (ex: \\\\frac{a}{b}).
        3. EXPLICATION : Démonstration complète aérée avec **Gras** et \\\\n\\\\n.
        4. INNOVATION : Pas de recopie. Change les données.
        
        FORMAT JSON :
        { \"questions\": [ { \"type\": \"single\", \"question\": \"\", \"options\": [], \"answer\": \"\", \"explanation\": \"\", \"difficulty\": \"Moyen\" } ] }";

        $response = wp_remote_post($this->api_url, [
            'headers' => ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => 'deepseek-chat',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.8
            ]),
            'timeout' => 120,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) return false;
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['usage'])) {
            Zonebac_Settings_Model::log_api_usage('deepseek', $data['usage']['prompt_tokens'], $data['usage']['completion_tokens']);
        }
        return $data['choices'][0]['message']['content'] ?? null;
    }
}
