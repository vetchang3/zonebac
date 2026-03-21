<?php
if (!defined('ABSPATH')) exit;

class Zonebac_Gemini_API
{

    /**
     * Récupère la clé API Gemini depuis les réglages de l'extension
     */
    private static function get_api_key()
    {
        // Utilisation de ton modèle de paramètres existant
        $settings = Zonebac_Settings_Model::get_settings();
        error_log("ZB SETTINGS " . $settings);

        return $settings['gemini_key'] ?? '';
    }

    public static function call_gemini_vision($base64_data, $prompt)
    {
        $api_key = self::get_api_key();

        if (empty($api_key)) {
            error_log("ZB GEMINI ERROR: Clé API manquante.");
            return "Erreur : Clé API non configurée.";
        }

        // Utilisation de l'URL validée par ton test (Gemini 3 Flash Preview)
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $api_key;

        $body = [
            "contents" => [[
                "parts" => [
                    ["text" => $prompt],
                    ["inline_data" => [
                        "mime_type" => "application/pdf", // Adapté pour tes fichiers PDF
                        "data" => $base64_data
                    ]]
                ]
            ]]
        ];

        $response = wp_remote_post($url, [
            'body'    => json_encode($body),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 120 // 2 minutes pour laisser le temps à l'OCR de traiter le PDF
        ]);

        if (is_wp_error($response)) {
            error_log("ZB GEMINI ERROR: " . $response->get_error_message());
            return "Erreur de connexion Gemini.";
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $raw_body  = wp_remote_retrieve_body($response);

        if ($http_code !== 200) {
            error_log("ZB GEMINI ERROR: Code $http_code. Réponse : " . $raw_body);
            return "Erreur API Gemini (Code $http_code).";
        }

        $result = json_decode($raw_body, true);

        // Extraction précise basée sur la structure validée en test
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }

        error_log("ZB GEMINI ERROR: Structure JSON inattendue.");
        return "Échec extraction Gemini.";
    }
}
