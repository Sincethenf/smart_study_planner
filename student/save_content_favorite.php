<?php
// student/save_content_favorite.php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['title']) || !isset($data['content']) || !isset($data['type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$title = $data['title'];
$content = $data['content'];
$type = $data['type']; // 'essay' or 'summarize'

try {
    $stmt = $conn->prepare("INSERT INTO favorites (user_id, content_type, content_data, title) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $type, $content, $title);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Content saved to favorites!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
