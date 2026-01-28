<?php
// 세션 시작 (필요한 경우)
session_start();

// 1. DB 연결
try {
    require_once 'db_connect.php';
} catch (Exception $e) {
    die("<script>alert('DB 연결 실패'); history.back();</script>");
}

// 2. POST 데이터 수신
$user_id   = $_POST['user_id'] ?? '';
$user_name = $_POST['user_name'] ?? '';
$password  = $_POST['password'] ?? '';
$email     = $_POST['email'] ?? '';

// 필수 값 검증
if (!$user_id || !$user_name || !$password || !$email) {
    echo "<script>alert('모든 항목을 입력해주세요.'); history.back();</script>";
    exit;
}

try {
    // 3. 아이디 중복 확인
    $checkSql = "SELECT COUNT(*) FROM user_tab WHERE user_id = :user_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':user_id' => $user_id]);
    
    if ($checkStmt->fetchColumn() > 0) {
        echo "<script>alert('이미 존재하는 아이디입니다.'); history.back();</script>";
        exit;
    }

    // 4. 비밀번호 암호화
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 5. 회원 정보 저장
    $sql = "INSERT INTO user_tab (user_id, password, email, user_name) 
            VALUES (:user_id, :password, :email, :user_name)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':user_id'   => $user_id,
        ':password'  => $hashedPassword,
        ':email'     => $email,
        ':user_name' => $user_name
    ]);

    if ($result) {
        // 성공 시 자바스크립트 실행 (HTML 헤더 없이 바로 출력)
        echo "<script type='text/javascript'>
                alert('회원가입이 완료되었습니다! 로그인 해주세요.');
                location.replace('login.php');
              </script>";
        exit;
    } else {
        echo "<script>alert('가입 처리 중 오류가 발생했습니다.'); history.back();</script>";
        exit;
    }

} catch (PDOException $e) {
    // 오류 메시지에 따옴표가 섞여 스크립트가 깨지는 것을 방지
    $errorMsg = addslashes($e->getMessage());
    echo "<script>alert('DB 오류: $errorMsg'); history.back();</script>";
    exit;
}
?>