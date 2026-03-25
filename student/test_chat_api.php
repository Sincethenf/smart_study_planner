<?php
// Test Gemini API connection
$apiKey = 'AIzaSyDKtuLyWJqaYnms-eY-fTWSNis319pTNfE';
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

$message = "Hello, can you help me study?";

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $message]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 500
    ]
];

$ch = curl_init($apiUrl . '?key=' . $apiKey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<h2>Debug Info:</h2>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>cURL Error:</strong> " . ($curlError ?: 'None') . "</p>";
echo "<h3>Response:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    echo "<h3>Parsed Response:</h3>";
    echo "<pre>" . print_r($result, true) . "</pre>";
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        echo "<h3>AI Response:</h3>";
        echo "<p>" . htmlspecialchars($result['candidates'][0]['content']['parts'][0]['text']) . "</p>";
    }
}
