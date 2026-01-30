<?php
session_start(); // [추가] 세션 시작
header('Content-Type: application/json');
require_once 'db_connect.php';

// 1. 로그인 체크
if (!isset($_SESSION['user_idx'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$id = $_POST['id'] ?? '';
$user_idx = $_SESSION['user_idx']; // 세션에서 현재 유저 번호 가져오기

if (!$id) {
    echo json_encode(['success' => false, 'message' => '삭제할 일정의 ID를 찾을 수 없습니다.']);
    exit;
}

try {
    // 2. [변경점] ID뿐만 아니라 user_idx가 일치하는지도 확인하여 본인 것만 삭제 가능하게 함
    $sql = "DELETE FROM user_schedules WHERE id = :id AND user_idx = :user_idx";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':user_idx' => $user_idx
    ]);

    // 실제로 삭제된 행이 있는지 확인
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '삭제 권한이 없거나 이미 삭제된 일정입니다.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '삭제 중 오류 발생: ' . $e->getMessage()]);
}
?>