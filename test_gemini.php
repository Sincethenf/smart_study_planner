<?php
// Test script to list available Gemini models
$apiKey = 'AIzaSyDKtuLyWJqaYnms-eY-fTWSNis319pTNfE';
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey;

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>HTTP Code: $httpCode</h2>";
echo "<h3>Available Models:</h3>";
echo "<pre>";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['models'])) {
        foreach ($data['models'] as $model) {
            echo "Model: " . $model['name'] . "\n";
            if (isset($model['supportedGenerationMethods'])) {
                echo "  Supported methods: " . implode(', ', $model['supportedGenerationMethods']) . "\n";
            }
            echo "\n";
        }
    } else {
        echo "No models found in response\n";
        print_r($data);
    }
} else {
    echo "Error: HTTP $httpCode\n";
    echo $response;
}

echo "</pre>";
?>
