<?php
// minjun_input.php
header('Content-Type: application/json; charset=utf-8');

// 1. DB 연결
try {
    require_once 'db_connect.php'; 
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}

// 2. POST 데이터 수신
$id        = $_POST['id'] ?? null; // 수정 시 전달받는 기존 일정 ID
$date      = $_POST['schedule_date'] ?? null;
$type      = $_POST['schedule_type'] ?? null;
$startTime = $_POST['start_time'] ?? null;
$endTime   = $_POST['end_time'] ?? null;
$note      = $_POST['plan_note'] ?? '';
$mode      = $_POST['mode'] ?? null; 

if (!$date || !$type) {
    echo json_encode(['success' => false, 'message' => '필수 데이터가 누락되었습니다.']);
    exit;
}

// 3. 타입별 실제 비교 시간 계산
$calcStart = $startTime;
$calcEnd   = $endTime;

if ($type === 'M') { 
    $calcStart = '07:00:00'; $calcEnd = '15:30:00'; 
} else if ($type === 'K') { 
    $calcStart = '13:00:00'; $calcEnd = '21:30:00'; 
} else if ($type === 'A') { 
    $calcStart = '10:00:00'; $calcEnd = '18:30:00'; 
} else if ($type === 'OFF') { 
    $calcStart = '00:00:00'; $calcEnd = '23:59:59'; 
}

try {
    $pdo->beginTransaction();

    // [수정/덮어쓰기 대응] 현재 수정 중인 본인 데이터는 중복 체크에서 제외하기 위해 잠시 삭제하거나 
    // 혹은 아래 중복 체크 쿼리에서 ID를 제외해야 합니다. 여기서는 기존 로직대로 유지하되 
    // 본인의 ID인 경우 먼저 삭제 처리합니다.
    if ($id) {
        $deleteCurrentSql = "DELETE FROM user_schedules WHERE id = :id";
        $deleteCurrentStmt = $pdo->prepare($deleteCurrentSql);
        $deleteCurrentStmt->execute([':id' => $id]);
    }

    // 4. 중복된 일정 체크
    $checkSql = "SELECT id, schedule_type, start_time, end_time, plan_note FROM user_schedules 
                 WHERE schedule_date = :date 
                 AND (
                    (start_time < :new_end AND end_time > :new_start)
                    OR (schedule_type = 'OFF') 
                    OR (:type = 'OFF')
                 )";
    
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':date' => $date,
        ':new_start' => $calcStart,
        ':new_end' => $calcEnd,
        ':type' => $type
    ]);
    
    $duplicates = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

    // [중요 수정] 중복이 있고, 강제 덮어쓰기(overwrite) 모드가 아닌 경우
    if (count($duplicates) > 0 && $mode !== 'overwrite') {
        $pdo->rollBack();

        // 중복된 일정 내용을 읽기 쉬운 텍스트로 변환
        $info_list = [];
        foreach ($duplicates as $dup) {
            $time_info = ($dup['schedule_type'] === 'OFF') ? "전일" : substr($dup['start_time'], 0, 5) . "~" . substr($dup['end_time'], 0, 5);
            $info_list[] = "- 타입: {$dup['schedule_type']} ({$time_info})\n  내용: {$dup['plan_note']}";
        }
        $existing_info = implode("\n", $info_list);

        echo json_encode([
            'success' => false, 
            'error_type' => 'DUPLICATE',
            'existing_info' => $existing_info // JS 알림창에 띄울 정보
        ]);
        exit;
    } 
    
    // 덮어쓰기 모드라면 겹치는 다른 일정들도 삭제
    if (count($duplicates) > 0 && $mode === 'overwrite') {
        foreach ($duplicates as $dup) {
            $delSql = "DELETE FROM user_schedules WHERE id = :dup_id";
            $delStmt = $pdo->prepare($delSql);
            $delStmt->execute([':dup_id' => $dup['id']]);
        }
    }

    // 5. 일정 등록 (INSERT)
    $sql = "INSERT INTO user_schedules (schedule_date, schedule_type, start_time, end_time, plan_note) 
            VALUES (:date, :type, :start, :end, :note)";
    $stmt = $pdo->prepare($sql);
    
    $finalStart = ($type === 'OFF') ? null : $calcStart;
    $finalEnd   = ($type === 'OFF') ? null : $calcEnd;

    $stmt->execute([
        ':date' => $date,
        ':type' => $type,
        ':start' => $finalStart,
        ':end' => $finalEnd,
        ':note' => $note
    ]);

    $pdo->commit();
    
    $msg = ($id) ? '일정이 성공적으로 수정되었습니다.' : '일정이 성공적으로 등록되었습니다.';
    echo json_encode(['success' => true, 'message' => $msg]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}
?>