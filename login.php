<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>업무 스케줄러 Pro - 로그인</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        :root { --primary: #4a90e2; }
        body { 
            font-family: 'Pretendard', sans-serif; 
            background-color: #f0f2f5; 
            padding: 40px 20px; 
            display: flex;
            align-items: center;
            min-height: 100vh;
        }
        #login-outer {
            max-width: 1000px;
            width: 100%;
            margin: 0 auto;
        }
        #login-container { 
            background: white; 
            padding: 60px; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
            /* 로그인 폼은 너무 넓으면 입력이 힘드므로 내부에서 중앙 정렬 */
            max-width: 500px;
            margin: 0 auto;
        }
        .form-label { font-weight: bold; color: #555; }
        .form-control { padding: 12px; border-radius: 8px; }
        .btn-primary { 
            background-color: var(--primary); 
            border: none; 
            padding: 15px;
            font-weight: 600;
            border-radius: 10px;
            font-size: 1.1rem;
        }
        .login-header { text-align: center; margin-bottom: 40px; }
        .input-group-text { cursor: pointer; background: white; }
    </style>
</head>
<body>

    <div id="login-outer">
        <div id="login-container">
            <div class="login-header">
                <h3 class="fw-bold">🔐 로그인</h3>
                <p class="text-muted">서비스 이용을 위해 로그인 해주세요.</p>
            </div>

            <form action="login_process.php" method="POST">
                <div class="mb-4">
                    <label for="user_id" class="form-label">아이디</label>
                    <input type="text" name="user_id" id="user_id" class="form-control" placeholder="아이디를 입력하세요" required>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">비밀번호</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" placeholder="비밀번호를 입력하세요" required>
                        <span class="input-group-text" onclick="togglePassword()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">로그인하기</button>
                
                <div class="text-center mt-4">
                    <span class="text-muted">아직 계정이 없으신가요?</span><br>
                    <a href="signup.php" class="text-decoration-none fw-bold" style="color: var(--primary);">회원가입 하러가기</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const pw = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (pw.type === 'password') {
                pw.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                pw.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
</body>
</html>