<?php
// db_connect.php
$config = include 'db_config.php';

$dsn = "mysql:host={$config['host']};dbname={$config['db']};charset={$config['charset']}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
    // 개발 중에는 아래 주석을 해제하여 연결을 확인하세요.
    // echo "DB 연결 성공"; 
} catch (\PDOException $e) {
    // 실제 서비스에서는 에러 로그를 남기고 사용자에게는 간단한 메시지만 보여줍니다.
    die("데이터베이스 연결 실패: " . $e->getMessage());
}