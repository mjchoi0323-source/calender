<?php
session_start();
require_once 'db_connect.php';

// 로그인 체크
if (!isset($_SESSION['user_idx'])) {
    header("Location: login.php");
    exit;
}

$user_idx = $_SESSION['user_idx'];

// 현재 로그인한 사용자의 정보 및 시간 설정 가져오기
try {
    // 기본 정보
    $sql = "SELECT user_id, user_name, email FROM user_tab WHERE idx = :idx";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idx' => $user_idx]);
    $user = $stmt->fetch();

    if (!$user) {
        die("사용자 정보를 찾을 수 없습니다.");
    }

    // 시간 설정 정보 가져오기 (M, A, K)
    $timeSql = "SELECT time_type, start_time, end_time FROM user_time_settings WHERE user_idx = :idx";
    $timeStmt = $pdo->prepare($timeSql);
    $timeStmt->execute([':idx' => $user_idx]);
    $settings = $timeStmt->fetchAll(PDO::FETCH_ASSOC);

    // 사용하기 편하게 배열 재구성
    $userTimes = [];
    foreach ($settings as $row) {
        $userTimes[$row['time_type']] = [
            'start' => substr($row['start_time'], 0, 5),
            'end'   => substr($row['end_time'], 0, 5)
        ];
    }

    // 기본값 설정
    $defaultTimes = [
        'M' => ['start' => '09:00', 'end' => '13:00'],
        'A' => ['start' => '13:00', 'end' => '18:00'],
        'K' => ['start' => '18:00', 'end' => '22:00']
    ];
    $times = array_merge($defaultTimes, $userTimes);

} catch (PDOException $e) {
    die("DB 오류: " . $e->getMessage());
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
        #edit-container { 
            background: white; 
            padding: 50px 60px; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
            max-width: 1000px; 
            margin: 0 auto; 
        }
        .form-label { font-weight: bold; color: #555; }
        .form-control:disabled { background-color: #e9ecef; }
        .btn-primary { background-color: var(--primary); border: none; padding: 12px; font-weight: 600; border-radius: 10px; }
        .input-group-text { cursor: pointer; background: white; }
        
        /* 캘린더 UI와 동일한 라디오 버튼 그룹 스타일 */
        .type-btn-group .btn-check:checked + .btn { 
            background-color: var(--primary); 
            color: white; 
            border-color: var(--primary); 
        }
        .time-edit-box {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border: 1px solid #dee2e6;
            margin-top: 15px;
        }
    </style>
</head>
<body>

    <div id="edit-container">
        <div class="text-center mb-5">
            <h3>👤 내 정보 및 업무 시간 설정</h3>
            <p class="text-muted">개인 정보와 사용자 정의 업무 시간(M, A, K)을 관리합니다.</p>
        </div>

        <form action="profile_process.php" method="POST" class="row g-4" id="profileForm">
            <div class="col-md-4">
                <label class="form-label">아이디</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['user_id']); ?>" disabled>
            </div>
            
            <div class="col-md-4">
                <label for="user_name" class="form-label">이름</label>
                <input type="text" name="user_name" id="user_name" class="form-control" value="<?php echo htmlspecialchars($user['user_name']); ?>" required>
            </div>

            <div class="col-md-4">
                <label for="email" class="form-label">이메일 주소</label>
                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="col-md-6">
                <label for="password" class="form-label">새 비밀번호 (변경 시에만)</label>
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
                <h5 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> 사용자 정의 업무 시간 설정</h5>
                <p class="small text-muted mb-4">M, A, K 버튼을 눌러 각 타입별 기준 시간을 수정하세요.</p>
                
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="btn-group w-100 type-btn-group mb-4" role="group">
                            <input type="radio" class="btn-check" name="time_view_tab" id="tab_M" value="M" checked onchange="switchTimeTab('M')">
                            <label class="btn btn-outline-primary py-3 fw-bold" for="tab_M">M (오전)</label>

                            <input type="radio" class="btn-check" name="time_view_tab" id="tab_A" value="A" onchange="switchTimeTab('A')">
                            <label class="btn btn-outline-primary py-3 fw-bold" for="tab_A">A (오후)</label>

                            <input type="radio" class="btn-check" name="time_view_tab" id="tab_K" value="K" onchange="switchTimeTab('K')">
                            <label class="btn btn-outline-primary py-3 fw-bold" for="tab_K">K (야간)</label>
                        </div>

                        <?php foreach(['M', 'A', 'K'] as $type): ?>
                        <div id="box_<?php echo $type; ?>" class="time-edit-box <?php echo $type === 'M' ? '' : 'd-none'; ?>">
                            <div class="row align-items-center">
                                <div class="col-5">
                                    <label class="form-label small">시작 시간</label>
                                    <input type="time" id="start_<?php echo $type; ?>" name="times[<?php echo $type; ?>][start]" class="form-control form-control-lg" value="<?php echo $times[$type]['start']; ?>">
                                </div>
                                <div class="col-2 text-center mt-4">
                                    <i class="bi bi-arrow-right fs-4 text-muted"></i>
                                </div>
                                <div class="col-5">
                                    <label class="form-label small">종료 시간</label>
                                    <input type="time" id="end_<?php echo $type; ?>" name="times[<?php echo $type; ?>][end]" class="form-control form-control-lg" value="<?php echo $times[$type]['end']; ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-5">
                <button type="submit" class="btn btn-primary w-100 mb-3 py-3 fs-5">전체 정보 업데이트</button>
                <div class="text-center">
                    <a href="calender.php" class="text-decoration-none text-muted">취소하고 돌아가기</a>
                </div>
            </div>
        </form>
    </div>

    <script>
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

        function switchTimeTab(type) {
            document.querySelectorAll('.time-edit-box').forEach(box => {
                box.classList.add('d-none');
            });
            document.getElementById('box_' + type).classList.remove('d-none');
        }

        // 폼 제출 시 유효성 검사 (비밀번호 및 시간 논리 체크)
        document.getElementById('profileForm').onsubmit = function() {
            // 1. 비밀번호 확인
            const pw = document.getElementById('password').value;
            const pwRe = document.getElementById('password_re').value;
            if (pw !== "" && pw !== pwRe) {
                alert("새 비밀번호가 일치하지 않습니다.");
                return false;
            }

            // 2. 시간 설정 유효성 검사 (시작 시간 < 종료 시간)
            const types = ['M', 'A', 'K'];
            const typeNames = {'M': '오전(M)', 'A': '오후(A)', 'K': '야간(K)'};

            for (let type of types) {
                const startTime = document.getElementById('start_' + type).value;
                const endTime = document.getElementById('end_' + type).value;

                if (startTime && endTime) {
                    if (startTime >= endTime) {
                        alert(typeNames[type] + "의 종료 시간은 시작 시간보다 늦어야 합니다.");
                        switchTimeTab(type); // 해당 탭으로 자동 이동
                        document.getElementById('tab_' + type).checked = true;
                        return false;
                    }
                }
            }

            return true;
        };
    </script>
</body>
</html>