<?php
session_start();
require_once 'db_connect.php';

// 로그인 체크
if (!isset($_SESSION['user_idx'])) {
    echo "<script>alert('접근 권한이 없습니다.'); location.href='login.php';</script>";
    exit;
}

$user_idx = $_SESSION['user_idx'];
$user_name = $_POST['user_name'] ?? '';
$email     = $_POST['email'] ?? '';
$password  = $_POST['password'] ?? '';
$times     = $_POST['times'] ?? []; // M, A, K 시간 데이터

// 필수 값 검증
if (!$user_name || !$email) {
    echo "<script>alert('이름과 이메일은 필수 입력 항목입니다.'); history.back();</script>";
    exit;
}

try {
    $pdo->beginTransaction(); // 여러 테이블 수정을 위해 트랜잭션 시작

    // 1. 기본 정보 업데이트 (이름, 이메일)
    $sql = "UPDATE user_tab SET user_name = :user_name, email = :email";
    $params = [
        ':user_name' => $user_name,
        ':email'     => $email,
        ':idx'       => $user_idx
    ];

    // 비밀번호 변경 여부 확인
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql .= ", password = :password";
        $params[':password'] = $hashedPassword;
    }

    $sql .= " WHERE idx = :idx";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 2. 시간 설정 업데이트 (M, A, K 루프)
    if (!empty($times)) {
        $timeSql = "INSERT INTO user_time_settings (user_idx, time_type, start_time, end_time) 
                    VALUES (:uid, :type, :start, :end)
                    ON DUPLICATE KEY UPDATE 
                    start_time = VALUES(start_time), 
                    end_time = VALUES(end_time)";
        
        $timeStmt = $pdo->prepare($timeSql);

        foreach ($times as $type => $val) {
            $timeStmt->execute([
                ':uid'   => $user_idx,
                ':type'  => $type,
                ':start' => $val['start'],
                ':end'   => $val['end']
            ]);
        }
    }

    $pdo->commit(); // 모든 변경사항 확정

    // 세션 이름 갱신
    $_SESSION['user_name'] = $user_name;

    echo "<script>
            alert('회원 정보 및 시간 설정이 성공적으로 수정되었습니다.');
            location.href = 'calender.php';
          </script>";
    exit;

} catch (PDOException $e) {
    $pdo->rollBack(); // 오류 발생 시 되돌리기
    $errorMsg = addslashes($e->getMessage());
    echo "<script>alert('데이터 처리 중 오류가 발생했습니다: $errorMsg'); history.back();</script>";
}
?>