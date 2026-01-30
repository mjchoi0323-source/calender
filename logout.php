<?php
session_start();

// 모든 세션 변수 제거
$_SESSION = array();

// 세션 쿠키를 삭제하고 싶을 경우 (권장)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 최종적으로 세션 파괴
session_destroy();

// 로그인 페이지로 이동
header("Location: login.php");
exit;