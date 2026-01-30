<?php
session_start();
header('Content-Type: application/json');

// 1. DB 연결
try {
    require_once 'db_connect.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패: ' . $e->getMessage()]);
    exit;
}

// 2. 로그인 체크
if (!isset($_SESSION['user_idx'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user_idx = $_SESSION['user_idx'];
$id = $_POST['id'] ?? null;
$date = $_POST['schedule_date'];
$type = $_POST['schedule_type'];
$note = $_POST['plan_note'] ?? '';
$mode = $_POST['mode'] ?? '';

// 시간 설정
$start_time = ($type === 'ETC') ? ($_POST['start_time'] ?? null) : null;
$end_time = ($type === 'ETC') ? ($_POST['end_time'] ?? null) : null;

try {
    // --- [중복 체크 로직 수정] ---
    // 신규 등록 시, '날짜'뿐만 아니라 '타입'과 '시작 시간'까지 똑같은 일정이 있는지 확인
    if (!$id && $mode !== 'overwrite') {
        $checkSql = "SELECT plan_note FROM user_schedules 
                    WHERE user_idx = :user_idx 
                    AND schedule_date = :date 
                    AND schedule_type = :type";
        
        $checkParams = [':user_idx' => $user_idx, ':date' => $date, ':type' => $type];

        // ETC 타입인 경우 시작 시간까지 체크해서 다른 시간이면 중복으로 안 치게 함
        if ($type === 'ETC') {
            $checkSql .= " AND start_time = :st";
            $checkParams[':st'] = $start_time;
        }

        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute($checkParams);
        
        if ($row = $checkStmt->fetch()) {
            echo json_encode([
                'success' => false, 
                'error_type' => 'DUPLICATE', 
                'existing_info' => $row['plan_note']
            ]);
            exit;
        }
    }

    if ($id) {
        // --- [수정 모드] ---
        $sql = "UPDATE user_schedules 
                SET schedule_date = :date, 
                    schedule_type = :type, 
                    start_time = :st, 
                    end_time = :et, 
                    plan_note = :note 
                WHERE id = :id AND user_idx = :user_idx";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':date' => $date, ':type' => $type, ':st' => $start_time, 
            ':et' => $end_time, ':note' => $note, ':id' => $id, ':user_idx' => $user_idx
        ]);
        $msg = "일정이 수정되었습니다.";
    } else {
        // --- [신규 등록 모드] ---
        // 덮어쓰기 시에도 '해당 날짜의 동일한 타입/시간'만 삭제하도록 수정
        if ($mode === 'overwrite') {
            $delSql = "DELETE FROM user_schedules WHERE user_idx = :user_idx AND schedule_date = :date AND schedule_type = :type";
            $delParams = [':user_idx' => $user_idx, ':date' => $date, ':type' => $type];
            if ($type === 'ETC') {
                $delSql .= " AND start_time = :st";
                $delParams[':st'] = $start_time;
            }
            $pdo->prepare($delSql)->execute($delParams);
        }

        $sql = "INSERT INTO user_schedules (user_idx, schedule_date, schedule_type, start_time, end_time, plan_note) 
                VALUES (:user_idx, :date, :type, :st, :et, :note)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_idx' => $user_idx, ':date' => $date, ':type' => $type, 
            ':st' => $start_time, ':et' => $end_time, ':note' => $note
        ]);
        $msg = "새 일정이 등록되었습니다.";
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '데이터 저장 오류: ' . $e->getMessage()]);
}
?>