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

// 필수 값 검증
if (!$user_name || !$email) {
    echo "<script>alert('이름과 이메일은 필수 입력 항목입니다.'); history.back();</script>";
    exit;
}

try {
    // 1. 기본 업데이트 SQL (이름, 이메일)
    $sql = "UPDATE user_tab SET user_name = :user_name, email = :email";
    $params = [
        ':user_name' => $user_name,
        ':email'     => $email,
        ':idx'       => $user_idx
    ];

    // 2. 비밀번호가 입력된 경우 쿼리에 추가
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql .= ", password = :password";
        $params[':password'] = $hashedPassword;
    }

    $sql .= " WHERE idx = :idx";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);

    if ($result) {
        // 수정된 이름을 세션에도 반영 (캘린더 상단 표시용)
        $_SESSION['user_name'] = $user_name;
        
        echo "<script>
                alert('회원 정보가 성공적으로 수정되었습니다.');
                location.href = 'calender.php';
              </script>";
        exit;
    } else {
        echo "<script>alert('정보 수정에 실패했습니다.'); history.back();</script>";
    }

} catch (PDOException $e) {
    $errorMsg = addslashes($e->getMessage());
    echo "<script>alert('DB 오류가 발생했습니다: $errorMsg'); history.back();</script>";
}
?>