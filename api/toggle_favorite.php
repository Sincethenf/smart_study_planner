<?php
// api/toggle_favorite.php
require_once '../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $content_id = $_POST['content_id'];
    $content_type = $_POST['content_type'];
    
    // Check if already favorited
    $check = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND content_id = ? AND content_type = ?");
    $check->bind_param("iis", $user_id, $content_id, $content_type);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        // Remove from favorites
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND content_id = ? AND content_type = ?");
        $stmt->bind_param("iis", $user_id, $content_id, $content_type);
        $stmt->execute();
        echo json_encode(['status' => 'removed']);
    } else {
        // Add to favorites
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, content_id, content_type) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $content_id, $content_type);
        $stmt->execute();
        echo json_encode(['status' => 'added']);
    }
}
?>