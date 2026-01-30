<?php
session_start();
header('Content-Type: application/json');

try {
    require_once 'db_connect.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패: ' . $e->getMessage()]);
    exit;
}

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

$standard_times = [
    'M'   => ['07:00:00', '15:30:00'],
    'K'   => ['13:00:00', '21:30:00'],
    'A'   => ['10:00:00', '18:30:00'],
    'OFF' => ['00:00:00', '23:59:59']
];

if ($type === 'ETC') {
    $start_time = $_POST['start_time'] ?? '00:00:00';
    $end_time = $_POST['end_time'] ?? '23:59:59';
} else {
    $start_time = $standard_times[$type][0];
    $end_time = $standard_times[$type][1];
}

try {
    // 1. 중복 체크 (수정 모드이거나 신규 등록일 때, 덮어쓰기 모드가 아닌 경우 실행)
    if ($mode !== 'overwrite') {
        $checkSql = "SELECT id, schedule_type, plan_note, start_time, end_time 
                    FROM user_schedules 
                    WHERE user_idx = :user_idx 
                    AND schedule_date = :date 
                    AND (
                        (COALESCE(start_time, '00:00:00') < :new_end) AND 
                        (COALESCE(end_time, '23:59:59') > :new_start)
                    )";
        
        // 수정 중일 때는 자기 자신(id)은 중복 대상에서 제외
        if ($id) {
            $checkSql .= " AND id != :id";
        }

        $checkParams = [
            ':user_idx' => $user_idx,
            ':date' => $date,
            ':new_start' => $start_time,
            ':new_end' => $end_time
        ];
        if ($id) $checkParams[':id'] = $id;

        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute($checkParams);
        $overlap = $checkStmt->fetch();
        
        if ($overlap) {
            $existing_info = "[{$overlap['schedule_type']}] " . substr($overlap['start_time'], 0, 5) . "~" . substr($overlap['end_time'], 0, 5) . " : " . $overlap['plan_note'];
            echo json_encode([
                'success' => false, 
                'error_type' => 'DUPLICATE', 
                'existing_info' => $existing_info
            ]);
            exit;
        }
    }

    // 2. 덮어쓰기 모드인 경우 겹치는 일정 삭제
    if ($mode === 'overwrite') {
        $delSql = "DELETE FROM user_schedules 
                   WHERE user_idx = :user_idx 
                   AND schedule_date = :date 
                   AND (COALESCE(start_time, '00:00:00') < :new_end) 
                   AND (COALESCE(end_time, '23:59:59') > :new_start)";
        
        // 수정 중일 때는 나 자신은 지우지 않음 (아래에서 UPDATE 할 것이므로)
        if ($id) {
            $delSql .= " AND id != :id";
        }

        $delParams = [':user_idx' => $user_idx, ':date' => $date, ':new_start' => $start_time, ':new_end' => $end_time];
        if ($id) $delParams[':id'] = $id;

        $pdo->prepare($delSql)->execute($delParams);
    }

    // 3. 저장 또는 수정 실행
    if ($id) {
        $sql = "UPDATE user_schedules 
                SET schedule_date = :date, schedule_type = :type, start_time = :st, end_time = :et, plan_note = :note 
                WHERE id = :id AND user_idx = :user_idx";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':date'=>$date, ':type'=>$type, ':st'=>$start_time, ':et'=>$end_time, ':note'=>$note, ':id'=>$id, ':user_idx'=>$user_idx]);
        $msg = "일정이 수정되었습니다.";
    } else {
        $sql = "INSERT INTO user_schedules (user_idx, schedule_date, schedule_type, start_time, end_time, plan_note) 
                VALUES (:user_idx, :date, :type, :st, :et, :note)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_idx'=>$user_idx, ':date'=>$date, ':type'=>$type, ':st'=>$start_time, ':et'=>$end_time, ':note'=>$note]);
        $msg = "새 일정이 등록되었습니다.";
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '데이터 저장 오류: ' . $e->getMessage()]);
}
?>