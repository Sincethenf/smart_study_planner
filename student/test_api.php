<?php
header('Content-Type: application/json');

$debugFile = __DIR__ . '/test_debug.log';
file_put_contents($debugFile, "Test file executed at " . date('Y-m-d H:i:s') . "\n");

echo json_encode([
    'success' => true,
    'message' => 'Test successful',
    'post' => $_POST,
    'method' => $_SERVER['REQUEST_METHOD']
]);
?>
