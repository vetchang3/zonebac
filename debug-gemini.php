<?php
// Script de test direct hors moteur Zonebac
$api_key = 'AIzaSyDm7dmY1vorFyxz8pIhRTCJrL-15Sn3GHU'; // Mets ta vraie clé ici pour le test

// TEST 1 : Modèle 1.5 Flash (Recommandé pour la vitesse)
$url_flash = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;

// TEST 2 : Ton URL actuelle (pour voir l'erreur exacte)
$url_actuelle = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $api_key;

function test_endpoint($url, $label)
{
    echo "<h3>Test de l'endpoint : $label</h3>";
    $payload = [
        "contents" => [["parts" => [["text" => "Réponds 'OK' si tu reçois ce message."]]]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Code HTTP : <strong>$http_code</strong><br>";
    echo "Réponse brute : <pre>" . htmlspecialchars($response) . "</pre><hr>";
}

test_endpoint($url_flash, "Gemini 1.5 Flash (Correct)");
test_endpoint($url_actuelle, "Ton URL actuelle (Probable 404)");
