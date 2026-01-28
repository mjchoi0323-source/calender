<?php
session_start(); // 세션 시작
require_once 'db_connect.php';

$user_id  = $_POST['user_id'] ?? '';
$password = $_POST['password'] ?? '';

if (!$user_id || !$password) {
    echo "<script>alert('아이디와 비밀번호를 모두 입력해주세요.'); history.back();</script>";
    exit;
}

try {
    // 1. 유저 정보 조회
    $sql = "SELECT idx, user_id, password, user_name FROM user_tab WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. 유저가 존재하고 비밀번호가 일치하는지 확인
    if ($user && password_verify($password, $user['password'])) {
        // 로그인 성공: 세션 저장
        $_SESSION['user_idx']  = $user['idx'];
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['user_name'] = $user['user_name'];

        echo "<script>alert('" . $user['user_name'] . "님, 환영합니다!'); location.href = 'calender.php';</script>";
    } else {
        // 로그인 실패
        echo "<script>alert('아이디 또는 비밀번호가 일치하지 않습니다.'); history.back();</script>";
    }

} catch (PDOException $e) {
    echo "<script>alert('DB 오류가 발생했습니다.'); history.back();</script>";
}
?>