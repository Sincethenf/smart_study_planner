<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$debugFile = __DIR__ . '/debug.log';
file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Script started\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$rawInput = file_get_contents('php://input');
parse_str($rawInput, $parsedData);

$notes = $parsedData['notes'] ?? $_POST['notes'] ?? '';
$type = $parsedData['type'] ?? $_POST['type'] ?? '';

file_put_contents($debugFile, "Notes length: " . strlen($notes) . "\n", FILE_APPEND);
file_put_contents($debugFile, "Type: " . $type . "\n", FILE_APPEND);

if (empty($notes) || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Notes and type are required']);
    exit;
}

// Google Gemini API Configuration
define('GOOGLE_API_KEY', 'AIzaSyAhM1n9OXbDQe9y4NNaHEnGS8etW0IGJw4');

function callGeminiAI($prompt)
{
    global $debugFile;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GOOGLE_API_KEY;

    $data = [
        'contents' => [[
            'parts' => [[
                'text' => $prompt
            ]]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    file_put_contents($debugFile, "API Response Code: $httpCode\n", FILE_APPEND);

    if ($httpCode !== 200) {
        file_put_contents($debugFile, "API Error: $response\n", FILE_APPEND);
        return null;
    }

    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

function generateMultipleChoice($notes)
{
    $prompt = "Based on the following notes, generate exactly 5 multiple choice questions. Each question should have 4 options (A, B, C, D) with only one correct answer. Format the output as JSON array with this structure: [{\"question\": \"...\", \"options\": {\"A\": \"...\", \"B\": \"...\", \"C\": \"...\", \"D\": \"...\"}, \"correct\": \"A\"}]. Here are the notes:\n\n$notes";

    $response = callGeminiAI($prompt);

    if (!$response) {
        return null;
    }

    preg_match('/\[.*\]/s', $response, $matches);
    if (!empty($matches[0])) {
        $questions = json_decode($matches[0], true);
        if ($questions && count($questions) >= 5) {
            return array_slice($questions, 0, 5);
        }
    }

    return null;
}

function generateEssay($notes)
{
    $prompt = "Based on the following notes, generate 5 essay questions that encourage critical thinking and deeper understanding. Format as JSON array: [{\"question\": \"...\", \"points\": \"...\"}]. Here are the notes:\n\n$notes";

    $response = callGeminiAI($prompt);

    if (!$response) {
        return null;
    }

    preg_match('/\[.*\]/s', $response, $matches);
    if (!empty($matches[0])) {
        return json_decode($matches[0], true);
    }

    return null;
}

function generateSummary($notes)
{
    $prompt = "Summarize the following notes in a clear, concise manner. Include key points and main concepts:\n\n$notes";

    return callGeminiAI($prompt);
}

$result = null;

switch ($type) {
    case 'quiz':
        $result = generateMultipleChoice($notes);
        break;
    case 'essay':
        $result = generateEssay($notes);
        break;
    case 'summarize':
        $result = generateSummary($notes);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        exit;
}

if ($result === null) {
    echo json_encode(['success' => false, 'message' => 'Failed to generate content. Please check your API key.']);
    exit;
}

echo json_encode([
    'success' => true,
    'type' => $type,
    'data' => $result
]);
