<?php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';

if (empty($message)) {
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Gemini API configuration
$apiKey = '';
// AIzaSyDUWOAwZKeR13yq-UxH7M0W04mU9q0Nw0Q
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

// Build context-aware prompt
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Student';

$systemPrompt = "You are a helpful AI study assistant for Smart Study Planner. You help students with their studies, answer questions about learning strategies, provide study tips, and assist with academic topics. Keep responses concise, friendly, and educational. The student's name is $user_name.";

$fullPrompt = "$systemPrompt\n\nStudent: $message\n\nAssistant:";

// Prepare API request
$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $fullPrompt]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 500,
        'topP' => 0.9,
        'topK' => 40
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

// Debug: Log the response
error_log("Gemini API Response - HTTP Code: $httpCode");
error_log("Gemini API Response Body: $response");
if ($curlError) error_log("cURL Error: $curlError");

if ($httpCode !== 200) {
    // Return more detailed error for debugging
    $errorDetail = json_decode($response, true);
    $errorMsg = isset($errorDetail['error']['message']) ? $errorDetail['error']['message'] : 'Unknown error';
    echo json_encode(['response' => "API Error (HTTP $httpCode): $errorMsg"]);
    exit;
}

$result = json_decode($response, true);

if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $aiResponse = trim($result['candidates'][0]['content']['parts'][0]['text']);
    echo json_encode(['response' => $aiResponse]);
} else {
    echo json_encode(['response' => "I'm not sure how to respond to that. Could you rephrase your question?"]);
}
