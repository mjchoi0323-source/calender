<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// 로그인 체크
if (!isset($_SESSION['user_idx'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user_idx = $_SESSION['user_idx'];

// 1. POST 데이터 받아오기
$id = $_POST['id'] ?? null;
$schedule_date = $_POST['schedule_date'] ?? null;
$schedule_type = $_POST['schedule_type'] ?? 'M';
$plan_note = $_POST['plan_note'] ?? '';
$mode = $_POST['mode'] ?? ''; // 'overwrite' 여부 확인

if (!$schedule_date) {
    echo json_encode(['success' => false, 'message' => '날짜를 선택해주세요.']);
    exit;
}

try {
    // 2. 사용자의 현재 시간 설정(M, A, K) 가져오기
    $time_settings = [
        'M' => ['start' => '07:00:00', 'end' => '15:30:00'],
        'A' => ['start' => '10:00:00', 'end' => '18:30:00'],
        'K' => ['start' => '13:00:00', 'end' => '21:30:00']
    ];

    $setStmt = $pdo->prepare("SELECT time_type, start_time, end_time FROM user_time_settings WHERE user_idx = :idx");
    $setStmt->execute([':idx' => $user_idx]);
    while ($row = $setStmt->fetch()) {
        $time_settings[$row['time_type']] = [
            'start' => $row['start_time'],
            'end'   => $row['end_time']
        ];
    }

    // 3. 타입에 따른 실제 저장 시간 결정 (이 시점의 시간을 DB에 '박제'함)
    $final_start = null;
    $final_end = null;

    if (in_array($schedule_type, ['M', 'A', 'K'])) {
        $final_start = $time_settings[$schedule_type]['start'];
        $final_end   = $time_settings[$schedule_type]['end'];
    } elseif ($schedule_type === 'ETC') {
        $final_start = $_POST['start_time'] ?? null;
        $final_end   = $_POST['end_time'] ?? null;
    }

    // 4. 중복 체크 (수정 모드가 아닐 때만 동일 날짜 중복 확인)
    if (!$id && $mode !== 'overwrite') {
        $checkSql = "SELECT id, schedule_type, start_time, end_time FROM user_schedules 
                     WHERE user_idx = :uid AND schedule_date = :sdate LIMIT 1";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':uid' => $user_idx, ':sdate' => $schedule_date]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $info = "타입: {$existing['schedule_type']} (시간: {$existing['start_time']}~{$existing['end_time']})";
            echo json_encode([
                'success' => false, 
                'error_type' => 'DUPLICATE', 
                'existing_info' => $info,
                'message' => '해당 날짜에 이미 일정이 있습니다.'
            ]);
            exit;
        }
    }

    // 5. 저장 실행 (INSERT 또는 UPDATE)
    if ($id) {
        // 수정 모드
        $sql = "UPDATE user_schedules SET 
                schedule_date = :sdate, 
                schedule_type = :stype, 
                start_time = :stime, 
                end_time = :etime, 
                plan_note = :note 
                WHERE id = :id AND user_idx = :uid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sdate' => $schedule_date,
            ':stype' => $schedule_type,
            ':stime' => $final_start,
            ':etime' => $final_end,
            ':note'  => $plan_note,
            ':id'    => $id,
            ':uid'   => $user_idx
        ]);
        $msg = "일정이 수정되었습니다.";
    } else {
        // 신규 등록 (덮어쓰기 모드 포함)
        if ($mode === 'overwrite') {
            $pdo->prepare("DELETE FROM user_schedules WHERE user_idx = :uid AND schedule_date = :sdate")
                ->execute([':uid' => $user_idx, ':sdate' => $schedule_date]);
        }

        $sql = "INSERT INTO user_schedules (user_idx, schedule_date, schedule_type, start_time, end_time, plan_note) 
                VALUES (:uid, :sdate, :stype, :stime, :etime, :note)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid'   => $user_idx,
            ':sdate' => $schedule_date,
            ':stype' => $schedule_type,
            ':stime' => $final_start,
            ':etime' => $final_end,
            ':note'  => $plan_note
        ]);
        $msg = "일정이 저장되었습니다.";
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}
?>