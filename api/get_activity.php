<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$days = isset($_GET['days']) ? intval($_GET['days']) : 7;

$data = [];
$labels = [];

for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('D', strtotime($date));
    
    $stmt = $conn->prepare("SELECT COALESCE(SUM(count), 0) as total 
                            FROM user_activity 
                            WHERE user_id = ? AND activity_date = ?");
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $data[] = $row['total'];
}

header('Content-Type: application/json');
echo json_encode(['labels' => $labels, 'data' => $data]);
?>