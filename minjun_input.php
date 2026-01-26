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

    // [추가 사항] 수정 모드이거나 overwrite 모드일 때 기존 ID 삭제 처리
    // 14시 -> 12시로 변경 시 기존 14시 일정을 먼저 지워야 중복 체크에 걸리지 않습니다.
    if ($id && ($mode === 'overwrite' || isset($_POST['id']))) {
        $deleteCurrentSql = "DELETE FROM user_schedules WHERE id = :id";
        $deleteCurrentStmt = $pdo->prepare($deleteCurrentSql);
        $deleteCurrentStmt->execute([':id' => $id]);
    }

    // 4. 중복된 일정 체크 (현재 등록하려는 시간과 겹치는 다른 일정이 있는지 확인)
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

    // 중복이 있고, 강제 덮어쓰기 모드가 아닌 경우
    if (count($duplicates) > 0 && $mode !== 'overwrite') {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'error_type' => 'DUPLICATE',
            'duplicate_list' => $duplicates
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
    // 수정이든 신규든 기존 것을 지웠으므로 새로 INSERT 합니다.
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