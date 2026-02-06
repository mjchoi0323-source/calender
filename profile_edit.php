<?php
session_start();
require_once 'db_connect.php';

// 로그인 체크
if (!isset($_SESSION['user_idx'])) {
    header("Location: login.php");
    exit;
}

$user_idx = $_SESSION['user_idx'];

try {
    // 사용자 기본 정보 가져오기
    $sql = "SELECT user_id, user_name, email FROM user_tab WHERE idx = :idx";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idx' => $user_idx]);
    $user = $stmt->fetch();

    if (!$user) {
        die("사용자 정보를 찾을 수 없습니다.");
    }

    // 시간 설정 정보 가져오기 (A, M, K)
    $time_sql = "SELECT time_type, start_time, end_time FROM user_time_settings WHERE user_idx = :idx";
    $time_stmt = $pdo->prepare($time_sql);
    $time_stmt->execute([':idx' => $user_idx]);
    $settings = $time_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 사용하기 편하게 배열 재정리
    $user_times = [];
    foreach ($settings as $s) {
        $user_times[$s['time_type']] = [
            'sh' => substr($s['start_time'], 0, 2),
            'sm' => substr($s['start_time'], 3, 2),
            'eh' => substr($s['end_time'], 0, 2),
            'em' => substr($s['end_time'], 3, 2)
        ];
    }
} catch (PDOException $e) {
    die("DB 오류: " . $e->getMessage());
}

// 시간 선택 옵션 생성 함수
function renderTimeSelect($name, $current_val, $is_hour = true) {
    $max = $is_hour ? 23 : 59;
    $step = 1; // 1단위로 설정 (이미지 기준)
    $html = "<select name='{$name}' class='form-select' style='width: 75px;'>";
    for ($i = 0; $i <= $max; $i += $step) {
        $val = sprintf("%02d", $i);
        $selected = ($val == $current_val) ? "selected" : "";
        $html .= "<option value='{$val}' {$selected}>{$val}</option>";
    }
    $html .= "</select>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>업무 스케줄러 Pro - 내 정보 수정</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root { --primary: #4a90e2; }
        body { font-family: 'Pretendard', sans-serif; background-color: #f0f2f5; padding: 40px 20px; }
        #edit-container { background: white; padding: 50px 60px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); max-width: 850px; margin: 0 auto; }
        .form-label { font-weight: bold; color: #555; }
        .btn-primary { background-color: var(--primary); border: none; padding: 12px; font-weight: 600; border-radius: 10px; }
        .time-setting-row { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .time-label { min-width: 50px; font-weight: bold; color: var(--primary); }
        .input-group-text { cursor: pointer; background: white; }
    </style>
</head>
<body>

    <div id="edit-container">
        <div class="text-center mb-4">
            <h3>👤 내 정보 및 시간 설정</h3>
            <p class="text-muted">이름, 이메일 및 업무 시간(A/M/K)을 관리합니다.</p>
        </div>

        <form action="profile_process.php" method="POST" class="row g-4">
            <div class="col-md-6">
                <label class="form-label">아이디</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['user_id']); ?>" disabled>
            </div>
            
            <div class="col-md-6">
                <label for="user_name" class="form-label">이름</label>
                <input type="text" name="user_name" id="user_name" class="form-control" value="<?php echo htmlspecialchars($user['user_name']); ?>" required>
            </div>

            <div class="col-12">
                <label for="email" class="form-label">이메일 주소</label>
                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <hr class="my-4">
            <h5 class="mb-3"><i class="bi bi-clock-history"></i> 업무 유형별 시간 설정</h5>
            
            <?php foreach (['A', 'M', 'K'] as $type): 
                $t = $user_times[$type] ?? ['sh'=>'00', 'sm'=>'00', 'eh'=>'00', 'em'=>'00'];
            ?>
            <div class="time-setting-row">
                <div class="time-label">유형 <?php echo $type; ?></div>
                <?php echo renderTimeSelect("times[{$type}][sh]", $t['sh'], true); ?>
                <span>:</span>
                <?php echo renderTimeSelect("times[{$type}][sm]", $t['sm'], false); ?>
                <span class="mx-2">~</span>
                <?php echo renderTimeSelect("times[{$type}][eh]", $t['eh'], true); ?>
                <span>:</span>
                <?php echo renderTimeSelect("times[{$type}][em]", $t['em'], false); ?>
            </div>
            <?php endforeach; ?>

            <hr class="my-4">
            <div class="text-primary fw-bold small"><i class="bi bi-info-circle"></i> 비밀번호 변경 시에만 입력하세요.</div>

            <div class="col-md-6">
                <label for="password" class="form-label">새 비밀번호</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" placeholder="변경할 비밀번호">
                    <span class="input-group-text" onclick="togglePassword('password', 'toggleIcon1')">
                        <i class="bi bi-eye-slash" id="toggleIcon1"></i>
                    </span>
                </div>
            </div>

            <div class="col-md-6">
                <label for="password_re" class="form-label">새 비밀번호 확인</label>
                <div class="input-group">
                    <input type="password" id="password_re" class="form-control" placeholder="비밀번호 재입력">
                    <span class="input-group-text" onclick="togglePassword('password_re', 'toggleIcon2')">
                        <i class="bi bi-eye-slash" id="toggleIcon2"></i>
                    </span>
                </div>
            </div>

            <div class="col-12 mt-5">
                <button type="submit" class="btn btn-primary w-100 mb-3">정보 및 시간 업데이트</button>
                <div class="text-center">
                    <a href="calender.php" class="text-decoration-none text-muted">취소하고 돌아가기</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        // 비밀번호 표시/숨기기 토글 함수
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        }

        // 폼 제출 시 유효성 검사
        document.querySelector('form').onsubmit = function() {
            const pw = document.getElementById('password').value;
            const pwRe = document.getElementById('password_re').value;
            if (pw !== "" && pw !== pwRe) {
                alert("새 비밀번호가 일치하지 않습니다.");
                return false;
            }
            return true;
        };
    </script>
</body>
</html>