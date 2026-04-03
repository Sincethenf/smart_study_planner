<?php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

$debugFile = __DIR__ . '/save_debug.log';
file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Save quiz started\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
file_put_contents($debugFile, "Input: " . print_r($input, true) . "\n", FILE_APPEND);

$questions = $input['questions'] ?? [];

if (empty($questions)) {
    echo json_encode(['success' => false, 'message' => 'No questions provided']);
    exit;
}

$user_id = $_SESSION['user_id'];
$quiz_data = json_encode($questions);
$title = 'Quiz - ' . date('M d, Y h:i A');

file_put_contents($debugFile, "User ID: $user_id\n", FILE_APPEND);
file_put_contents($debugFile, "Title: $title\n", FILE_APPEND);
file_put_contents($debugFile, "Quiz data length: " . strlen($quiz_data) . "\n", FILE_APPEND);

// Insert into favorites table
$stmt = $conn->prepare("INSERT INTO favorites (user_id, title, content_data, content_type, created_at) VALUES (?, ?, ?, 'quiz', NOW())");
$stmt->bind_param("iss", $user_id, $title, $quiz_data);

if ($stmt->execute()) {
    file_put_contents($debugFile, "Success!\n\n", FILE_APPEND);
    echo json_encode(['success' => true, 'message' => 'Quiz saved to favorites']);
} else {
    $error = $stmt->error;
    file_put_contents($debugFile, "Error: $error\n\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Failed to save quiz', 'error' => $error]);
}

$stmt->close();
?>
