<?php
session_start();
if (!isset($_SESSION['reset_user_idx'])) {
    echo "<script>alert('잘못된 접근입니다.'); location.href='login.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>비밀번호 재설정</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .reset-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
        .input-group-text { cursor: pointer; background: white; }
    </style>
</head>
<body>
    <div class="reset-card">
        <h3 class="text-center mb-4">🆕 새 비밀번호 설정</h3>
        <form action="reset_password_process.php" method="POST" id="resetForm">
            <div class="mb-3">
                <label class="form-label fw-bold">새 비밀번호</label>
                <div class="input-group">
                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                    <span class="input-group-text" onclick="togglePassword('new_password', 'icon1')">
                        <i class="bi bi-eye-slash" id="icon1"></i>
                    </span>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">비밀번호 확인</label>
                <div class="input-group">
                    <input type="password" id="new_password_re" class="form-control" required>
                    <span class="input-group-text" onclick="togglePassword('new_password_re', 'icon2')">
                        <i class="bi bi-eye-slash" id="icon2"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-success w-100 p-3 fw-bold">비밀번호 변경 완료</button>
        </form>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        }

        document.getElementById('resetForm').onsubmit = function() {
            if (document.getElementById('new_password').value !== document.getElementById('new_password_re').value) {
                alert("비밀번호가 서로 일치하지 않습니다.");
                return false;
            }
            return true;
        };
    </script>
</body>
</html> 