<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_idx'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user_idx = $_SESSION['user_idx'];

$id = $_POST['id'] ?? null;
$schedule_date = $_POST['schedule_date'] ?? null;
$schedule_type = $_POST['schedule_type'] ?? 'M';
$plan_note = $_POST['plan_note'] ?? '';
$mode = $_POST['mode'] ?? '';

if (!$schedule_date) {
    echo json_encode(['success' => false, 'message' => '날짜를 선택해주세요.']);
    exit;
}

try {
    // 2. 사용자의 현재 시간 설정 가져오기
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

    // 3. 타입에 따른 실제 저장 시간 결정
    $final_start = null;
    $final_end = null;

    if (in_array($schedule_type, ['M', 'A', 'K'])) {
        $final_start = $time_settings[$schedule_type]['start'];
        $final_end   = $time_settings[$schedule_type]['end'];
    } elseif ($schedule_type === 'ETC') {
        $final_start = $_POST['start_time'] ?? null;
        $final_end   = $_POST['end_time'] ?? null;
    }

    // 4. [수정] 시간 기반 중복 체크 (다중 일정 대응)
    if ($final_start && $final_end && $schedule_type !== 'OFF' && $mode !== 'overwrite') {
        
        $checkSql = "SELECT id, schedule_type, start_time, end_time, plan_note 
                     FROM user_schedules 
                     WHERE user_idx = :uid 
                       AND schedule_date = :sdate 
                       AND schedule_type != 'OFF'
                       AND start_time < :new_end 
                       AND end_time > :new_start";
        
        if ($id) {
            $checkSql .= " AND id != :id";
        }

        $checkStmt = $pdo->prepare($checkSql);
        $checkParams = [
            ':uid' => $user_idx, 
            ':sdate' => $schedule_date, 
            ':new_start' => $final_start, 
            ':new_end' => $final_end
        ];
        if ($id) $checkParams[':id'] = $id;
        
        $checkStmt->execute($checkParams);
        // fetchAll()을 사용하여 겹치는 모든 일정을 가져옴
        $existing_rows = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($existing_rows) > 0) {
            $info_list = [];
            foreach ($existing_rows as $row) {
                $st = substr($row['start_time'], 0, 5);
                $et = substr($row['end_time'], 0, 5);
                $info_list[] = "- [{$row['schedule_type']}] {$st}~{$et} : {$row['plan_note']}";
            }
            
            // 겹치는 일정들을 줄바꿈 문자(\n)로 연결
            $info_string = implode("\n", $info_list);

            echo json_encode([
                'success' => false, 
                'error_type' => 'DUPLICATE', 
                'existing_info' => $info_string,
                'message' => '입력하신 시간대에 겹치는 일정이 ' . count($existing_rows) . '건 있습니다.'
            ]);
            exit;
        }
    }

    // 5. 저장 실행 (INSERT 또는 UPDATE)
    if ($id) {
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
        if ($mode === 'overwrite') {
            $pdo->prepare("DELETE FROM user_schedules 
                           WHERE user_idx = :uid AND schedule_date = :sdate 
                           AND start_time < :new_end AND end_time > :new_start")
                ->execute([
                    ':uid' => $user_idx, 
                    ':sdate' => $schedule_date, 
                    ':new_start' => $final_start, 
                    ':new_end' => $final_end
                ]);
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