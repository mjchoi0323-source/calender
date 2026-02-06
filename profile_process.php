<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_idx'])) {
    echo "<script>alert('접근 권한이 없습니다.'); location.href='login.php';</script>";
    exit;
}

$user_idx = $_SESSION['user_idx'];
$user_name = $_POST['user_name'] ?? '';
$email     = $_POST['email'] ?? '';
$password  = $_POST['password'] ?? '';
$times     = $_POST['times'] ?? []; // A, M, K 시간 데이터

if (!$user_name || !$email) {
    echo "<script>alert('이름과 이메일은 필수 입력 항목입니다.'); history.back();</script>";
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. 기본 정보 업데이트
    $sql = "UPDATE user_tab SET user_name = :user_name, email = :email";
    $params = [':user_name' => $user_name, ':email' => $email, ':idx' => $user_idx];

    if (!empty($password)) {
        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
        $sql .= ", password = :password";
    }
    $sql .= " WHERE idx = :idx";
    $pdo->prepare($sql)->execute($params);

    // 2. 시간 설정(A, M, K) 업데이트
    foreach ($times as $type => $t) {
        $start_time = "{$t['sh']}:{$t['sm']}:00";
        $end_time   = "{$t['eh']}:{$t['em']}:00";

        // 기존 설정이 있는지 확인
        $check_sql = "SELECT setting_id FROM user_time_settings WHERE user_idx = :uid AND time_type = :type";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':uid' => $user_idx, ':type' => $type]);
        $setting_id = $check_stmt->fetchColumn();

        if ($setting_id) {
            // 존재하면 UPDATE
            $up_sql = "UPDATE user_time_settings SET start_time = :st, end_time = :et WHERE setting_id = :sid";
            $pdo->prepare($up_sql)->execute([':st' => $start_time, ':et' => $end_time, ':sid' => $setting_id]);
        } else {
            // 없으면 INSERT
            $in_sql = "INSERT INTO user_time_settings (user_idx, time_type, start_time, end_time) VALUES (:uid, :type, :st, :et)";
            $pdo->prepare($in_sql)->execute([':uid' => $user_idx, ':type' => $type, ':st' => $start_time, ':et' => $end_time]);
        }
    }

    $pdo->commit();
    $_SESSION['user_name'] = $user_name;

    echo "<script>alert('회원 정보와 업무 시간이 성공적으로 수정되었습니다.'); location.href = 'calender.php';</script>";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $errorMsg = addslashes($e->getMessage());
    echo "<script>alert('DB 오류가 발생했습니다: $errorMsg'); history.back();</script>";
}
?>