<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>업무 스케줄러 Pro - 회원가입</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        :root { --primary: #4a90e2; }
        body { 
            font-family: 'Pretendard', sans-serif; 
            background-color: #f0f2f5; 
            padding: 40px 20px; 
        }
        #signup-outer {
            max-width: 1000px;
            margin: 0 auto;
        }
        #signup-container { 
            background: white; 
            padding: 50px 60px; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
        }
        .form-label { font-weight: bold; color: #555; margin-bottom: 8px; }
        .form-control {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        /* 버튼이 포함된 입력 그룹 스타일 조정 */
        .input-group-text {
            background: white;
            cursor: pointer;
            border-top-right-radius: 8px !important;
            border-bottom-right-radius: 8px !important;
        }
        .btn-primary { 
            background-color: var(--primary); 
            border: none; 
            padding: 15px;
            font-weight: 600;
            border-radius: 10px;
            font-size: 1.1rem;
        }
        .signup-header {
            text-align: center;
            margin-bottom: 40px;
        }
    </style>
</head>
<body>

    <div id="signup-outer">
        <div id="signup-container">
            <div class="signup-header">
                <h3>👤 회원가입</h3>
                <p>나의 업무 스케줄 관리를 위한 첫 걸음</p>
            </div>

            <form action="signup_process.php" method="POST" class="row g-4">
                <div class="col-md-6">
                    <label for="user_id" class="form-label">아이디</label>
                    <input type="text" name="user_id" id="user_id" class="form-control" placeholder="사용할 아이디 입력" required>
                </div>
                
                <div class="col-md-6">
                    <label for="user_name" class="form-label">이름</label>
                    <input type="text" name="user_name" id="user_name" class="form-control" placeholder="실명 입력" required>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label">비밀번호</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" placeholder="8자 이상 입력" required>
                        <span class="input-group-text" onclick="togglePassword('password', this)">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="password_re" class="form-label">비밀번호 확인</label>
                    <div class="input-group">
                        <input type="password" id="password_re" class="form-control" placeholder="비밀번호 다시 입력" required>
                        <span class="input-group-text" onclick="togglePassword('password_re', this)">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="col-12">
                    <label for="email" class="form-label">이메일 주소</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="example@mail.com" required>
                </div>

                <div class="col-12 mt-5">
                    <button type="submit" class="btn btn-primary w-100">계정 만들기</button>
                    <div class="text-center mt-4">
                        <span class="text-muted">이미 회원이신가요?</span> 
                        <a href="login.php" class="text-decoration-none fw-bold" style="color: var(--primary);">로그인하기</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 비밀번호 보이기/숨기기 토글 함수
        function togglePassword(inputId, iconElement) {
            const input = document.getElementById(inputId);
            const icon = iconElement.querySelector('i');
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = "password";
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        // 제출 전 확인 스크립트
        document.querySelector('form').onsubmit = function() {
            const pw = document.getElementById('password').value;
            const pwRe = document.getElementById('password_re').value;
            if (pw !== pwRe) {
                alert("비밀번호가 일치하지 않습니다.");
                return false;
            }
            return true;
        };
    </script>
</body>
</html>