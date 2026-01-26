<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // DB 연결 파일 경로 확인

$id = $_POST['id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => '삭제할 일정의 ID를 찾을 수 없습니다.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM user_schedules WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}