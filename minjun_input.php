<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_idx'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user_idx = $_SESSION['user_idx'];

// 클라이언트로부터 전달받은 파라미터
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
    // 1. 사용자의 커스텀 시간 설정 가져오기
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

    // 2. 타입에 따른 실제 저장 시간 결정
    $final_start = '00:00:00';
    $final_end = '00:00:00';

    if (in_array($schedule_type, ['M', 'A', 'K'])) {
        $final_start = $time_settings[$schedule_type]['start'];
        $final_end   = $time_settings[$schedule_type]['end'];
    } elseif ($schedule_type === 'ETC') {
        $final_start = $_POST['start_time'] ?? '00:00:00';
        $final_end   = $_POST['end_time'] ?? '00:00:00';
    }

    $deleted_info = ""; // 삭제된 일정 정보를 담을 변수

    // 3. 기존 데이터 정리 로직 (OFF 전환 및 중복 처리)
    if ($schedule_type === 'OFF') {
        // [CASE: OFF로 등록/수정할 때] 
        // 수정 중인 본인($id)을 제외하고, 그날의 다른 모든 일정을 삭제합니다.
        
        // 삭제될 일정 목록 미리 가져오기 (알림용)
        $findSql = "SELECT schedule_type, start_time, end_time FROM user_schedules 
                    WHERE user_idx = :uid AND schedule_date = :sdate";
        $findParams = [':uid' => $user_idx, ':sdate' => $schedule_date];
        if ($id) {
            $findSql .= " AND id != :id";
            $findParams[':id'] = $id;
        }
        
        $findStmt = $pdo->prepare($findSql);
        $findStmt->execute($findParams);
        $to_delete = $findStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($to_delete) {
            $del_list = [];
            foreach ($to_delete as $row) {
                $del_list[] = "[{$row['schedule_type']}] " . substr($row['start_time'], 0, 5) . "~" . substr($row['end_time'], 0, 5);
            }
            $deleted_info = "\n(삭제된 일정: " . implode(", ", $del_list) . ")";
        }

        // 본인을 제외한 다른 일정 실제 삭제
        $delOtherSql = "DELETE FROM user_schedules WHERE user_idx = :uid AND schedule_date = :sdate";
        $delParams = [':uid' => $user_idx, ':sdate' => $schedule_date];
        if ($id) {
            $delOtherSql .= " AND id != :id";
            $delParams[':id'] = $id;
        }
        $pdo->prepare($delOtherSql)->execute($delParams);

    } else {
        // [CASE: 일반 일정(M,A,K,ETC)으로 등록/수정할 때]
        // 1. 해당 날짜에 OFF가 있다면 삭제 (본인 ID는 보호)
        $delOffSql = "DELETE FROM user_schedules WHERE user_idx = :uid AND schedule_date = :sdate AND schedule_type = 'OFF'";
        $delOffParams = [':uid' => $user_idx, ':sdate' => $schedule_date];
        if ($id) {
            $delOffSql .= " AND id != :id";
            $delOffParams[':id'] = $id;
        }
        $pdo->prepare($delOffSql)->execute($delOffParams);

        // 2. 시간 중복 체크 (덮어쓰기 모드가 아닐 때만)
        if ($mode !== 'overwrite') {
            $checkSql = "SELECT id, schedule_type, start_time, end_time, plan_note 
                         FROM user_schedules 
                         WHERE user_idx = :uid 
                           AND schedule_date = :sdate 
                           AND start_time < :new_end 
                           AND end_time > :new_start";
            
            $checkParams = [
                ':uid' => $user_idx, 
                ':sdate' => $schedule_date, 
                ':new_start' => $final_start, 
                ':new_end' => $final_end
            ];

            if ($id) {
                $checkSql .= " AND id != :id";
                $checkParams[':id'] = $id;
            }

            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute($checkParams);
            $existing_rows = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($existing_rows) > 0) {
                $info_list = [];
                foreach ($existing_rows as $row) {
                    $st = substr($row['start_time'], 0, 5);
                    $et = substr($row['end_time'], 0, 5);
                    $info_list[] = "- [{$row['schedule_type']}] {$st}~{$et} : {$row['plan_note']}";
                }
                echo json_encode([
                    'success' => false, 
                    'error_type' => 'DUPLICATE', 
                    'existing_info' => implode("\n", $info_list),
                    'message' => '입력하신 시간대에 겹치는 일정이 있습니다.'
                ]);
                exit;
            }
        }
    }

    // 4. 저장 실행 (INSERT 또는 UPDATE)
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
        $msg = "일정이 수정되었습니다." . $deleted_info;
    } else {
        // 신규 등록 모드 (덮어쓰기 처리 포함)
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
        $msg = "일정이 저장되었습니다." . $deleted_info;
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}
?>  