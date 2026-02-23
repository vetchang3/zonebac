<?php

class Zonebac_DeepSeek_API
{
    private $api_url = 'https://api.deepseek.com/v1/chat/completions';

    public function generate_questions_batch($params)
    {
        $api_key = Zonebac_Settings_Model::get_api_key();
        if (empty($api_key)) {
            return false;
        }

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

        if (is_wp_error($response)) {
            error_log("ZONEBAC API ERROR: " . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            error_log("ZONEBAC API STATUS ERROR ($status): " . $body);
            return false;
        }

        // Log de la réponse pour voir si le JSON est valide
        error_log("ZONEBAC DEBUG: Réponse brute reçue de DeepSeek.");

        $data = json_decode($body, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function build_expert_prompt($p)
    {
        // Sécurité : Valeurs par défaut si les clés sont absentes
        $p['f'] = $p['f'] ?? 30;
        $p['m'] = $p['m'] ?? 40;
        $p['d'] = $p['d'] ?? 30;

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
}
