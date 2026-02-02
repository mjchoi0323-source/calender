<?php
// 세션 시작 및 로그인 체크
session_start();
if (!isset($_SESSION['user_idx'])) {
    header("Location: login.php");
    exit;
}
$user_idx = $_SESSION['user_idx'];
$user_name = $_SESSION['user_name'] ?? '사용자'; 

// 1. DB 연결
try {
    require_once 'db_connect.php';
} catch (Exception $e) {
    die("DB 연결 실패: " . $e->getMessage());
}

// [추가] 2. 사용자의 커스텀 시간 설정 가져오기
$user_times = [
    'M' => ['start' => '07:00', 'end' => '15:30'], // 기본값
    'A' => ['start' => '10:00', 'end' => '18:30'],
    'K' => ['start' => '13:00', 'end' => '21:30']
];

$timeSql = "SELECT time_type, start_time, end_time FROM user_time_settings WHERE user_idx = :idx";
$timeStmt = $pdo->prepare($timeSql);
$timeStmt->execute([':idx' => $user_idx]);
while ($row = $timeStmt->fetch()) {
    $user_times[$row['time_type']] = [
        'start' => substr($row['start_time'], 0, 5),
        'end'   => substr($row['end_time'], 0, 5)
    ];
}

// 3. 로그인한 사용자의 일정만 가져오기
$sql = "SELECT id, schedule_date, schedule_type, start_time, end_time, plan_note 
        FROM user_schedules 
        WHERE user_idx = :user_idx";
$stmt = $pdo->prepare($sql);
$stmt->execute([':user_idx' => $user_idx]);
$events = [];

// ... 상단 PHP 루프 부분 수정 ...
while ($row = $stmt->fetch()) {
    $type = $row['schedule_type'];
    $title = "[" . $type . "] " . $row['plan_note'];
    
    $color = '#607d8b';
    if ($type === 'M') $color = '#4caf50';
    else if ($type === 'K') $color = '#ff9800';
    else if ($type === 'A') $color = '#2196f3';
    else if ($type === 'OFF') $color = '#f44336';

    $start_date = $row['schedule_date'];
    $end_date = $row['schedule_date'];

    // [중요 수정] 설정값을 참조하지 않고, DB에 저장된 시간을 그대로 사용합니다.
    // 이렇게 해야 나중에 설정을 바꿔도 과거 일정이 유지됩니다.
    if ($row['start_time'] && $row['end_time'] && $type !== 'OFF') {
        $full_start = $start_date . 'T' . $row['start_time'];
        $full_end = $end_date . 'T' . $row['end_time'];
    } else {
        $full_start = $start_date;
        $full_end = $end_date;
    }

    $events[] = [
        'id' => $row['id'],
        'title' => $title,
        'start' => $full_start,
        'end' => $full_end,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'extendedProps' => [
            'type' => $type, 
            'note' => $row['plan_note'],
            'raw_date' => $row['schedule_date'],
            'raw_start' => $row['start_time'],
            'raw_end' => $row['end_time']
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>업무 스케줄러 Pro - 캘린더</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        :root { --primary: #4a90e2; }
        body { font-family: 'Pretendard', sans-serif; background-color: #f0f2f5; padding: 20px; }
        #calendar-container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 15px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .fc-event-title { font-weight: 500; font-size: 0.85em; cursor: pointer; }
        .modal-content { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .modal-header { background-color: var(--primary); color: white; border-top-left-radius: 15px; border-top-right-radius: 15px; }
        .modal-header.bg-view { background-color: #5c6bc0; }
        #custom-time-container { display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef; }
        .view-label { font-weight: bold; color: #555; font-size: 14px; margin-bottom: 5px; display: block; }
        .view-value { padding: 10px 12px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; min-height: 45px; display: flex; align-items: center; }
        .user-header { max-width: 1000px; margin: 0 auto 10px; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

    <div class="user-header">
        <div><strong><?php echo htmlspecialchars($user_name); ?></strong> 님 환영합니다.</div>
        <div>
            <a href="profile_edit.php" class="btn btn-sm btn-outline-primary me-1">내 정보 수정</a>
            <a href="logout.php" class="btn btn-sm btn-outline-secondary">로그아웃</a>
        </div>
    </div>

    <div id="calendar-container">
        <h3 class="text-center mb-4">📅 나의 업무 스케줄</h3>
        <div id="calendar"></div>
    </div>

    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">새 스케줄 등록</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit-id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">날짜</label>
                        <input type="date" id="date-input" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">타입 선택</label>
                        <select id="type-select" class="form-select" onchange="toggleCustomTime()">
                            <option value="M">M (오전)</option>
                            <option value="A">A (통상)</option>
                            <option value="K">K (오후)</option>
                            <option value="OFF">Day Off (휴무)</option>
                            <option value="ETC">기타 (시간 직접 선택)</option>
                        </select>
                    </div>
                    <div id="custom-time-container" class="mb-3">
                        <label class="form-label fw-bold">시간 설정</label>
                        <div class="d-flex align-items-center gap-2">
                            <select id="start-hour" class="form-select form-select-sm"></select> : 
                            <select id="start-min" class="form-select form-select-sm"></select>
                            <span>~</span>
                            <select id="end-hour" class="form-select form-select-sm"></select> : 
                            <select id="end-min" class="form-select form-select-sm"></select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">계획 및 메모</label>
                        <input type="text" id="plan-input" class="form-control" placeholder="메모를 입력하세요.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="button" class="btn btn-primary" onclick="confirmAndSave()">저장하기</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-view">
                    <h5 class="modal-title text-white">일정 상세 정보</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="view-label">날짜</label>
                        <div class="view-value" id="view-date"></div>
                    </div>
                    <div class="mb-3">
                        <label class="view-label">근무 타입</label>
                        <div class="view-value"><span id="view-type" class="badge bg-primary fs-6"></span></div>
                    </div>
                    <div class="mb-3">
                        <label class="view-label">근무 시간</label>
                        <div class="view-value" id="view-time"></div>
                    </div>
                    <div class="mb-3">
                        <label class="view-label">계획 및 메모</label>
                        <div class="view-value" id="view-note" style="align-items: flex-start; min-height: 80px;"></div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-danger" onclick="deleteSchedule()">삭제하기</button>
                    <div>
                        <button type="button" class="btn btn-warning me-1" onclick="openEditModal()">수정하기</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let calendar;
        let scheduleModal, viewModal;
        let selectedEventId = null; 

        // [핵심] PHP에서 설정된 사용자 시간을 JS 객체로 전달
        const userTimeSettings = <?php echo json_encode($user_times); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
            viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
            
            initTimeOptions();
            updateSelectLabels(); // 모달 선택지 텍스트 업데이트

            const savedView = localStorage.getItem('lastView') || 'dayGridMonth';

            calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                initialView: savedView, 
                locale: 'ko',
                headerToolbar: { 
                    left: 'prev,next today', 
                    center: 'title', 
                    right: 'dayGridMonth,timeGridWeek' 
                },
                datesSet: function(info) {
                    localStorage.setItem('lastView', info.view.type);
                },
                events: <?php echo json_encode($events); ?>,
                dateClick: function(info) {
                    resetModal();
                    document.getElementById('date-input').value = info.dateStr.split('T')[0];
                    scheduleModal.show();
                },
                eventClick: function(info) {
                    const event = info.event;
                    selectedEventId = event.id;
                    const props = event.extendedProps;
                    
                    document.getElementById('view-date').innerText = props.raw_date;
                    document.getElementById('view-type').innerText = props.type;
                    document.getElementById('view-note').innerText = props.note || "(메모 없음)";
                    
                    if(props.type === 'OFF') {
                        document.getElementById('view-time').innerText = "휴무";
                    } else {
                        // FullCalendar event 객체의 start/end를 이용해 시간 표시
                        const fmt = (d) => d.getHours().toString().padStart(2,'0')+":"+d.getMinutes().toString().padStart(2,'0');
                        document.getElementById('view-time').innerText = `${fmt(event.start)} ~ ${fmt(event.end)}`;
                    }
                    viewModal.show();
                }
            });
            calendar.render();
        });

        // 모달창의 M, A, K 선택지에 사용자 정의 시간 표시
        function updateSelectLabels() {
            const select = document.getElementById('type-select');
            for(let opt of select.options) {
                if(userTimeSettings[opt.value]) {
                    opt.text = `${opt.value} | ${userTimeSettings[opt.value].start} - ${userTimeSettings[opt.value].end}`;
                }
            }
        }

        function initTimeOptions() {
            const hSelects = [document.getElementById('start-hour'), document.getElementById('end-hour')];
            const mSelects = [document.getElementById('start-min'), document.getElementById('end-min')];
            hSelects.forEach(s => { for(let i=0; i<24; i++) s.add(new Option(i.toString().padStart(2,'0'), i.toString().padStart(2,'0'))); });
            mSelects.forEach(s => { for(let i=0; i<60; i+=5) s.add(new Option(i.toString().padStart(2,'0'), i.toString().padStart(2,'0'))); });
        }

        function toggleCustomTime() {
            document.getElementById('custom-time-container').style.display = 
                (document.getElementById('type-select').value === 'ETC') ? 'block' : 'none';
        }

        function resetModal() {
            document.getElementById('modalTitle').innerText = "새 스케줄 등록";
            document.getElementById('edit-id').value = "";
            document.getElementById('plan-input').value = "";
            document.getElementById('type-select').value = "M";
            toggleCustomTime();
        }

        function openEditModal() {
            const event = calendar.getEventById(selectedEventId);
            const props = event.extendedProps;
            
            document.getElementById('modalTitle').innerText = "스케줄 수정";
            document.getElementById('edit-id').value = selectedEventId;
            document.getElementById('date-input').value = props.raw_date;
            document.getElementById('type-select').value = props.type;
            document.getElementById('plan-input').value = props.note;
            
            if(props.type === 'ETC' && props.raw_start) {
                document.getElementById('start-hour').value = props.raw_start.split(':')[0];
                document.getElementById('start-min').value = props.raw_start.split(':')[1];
                document.getElementById('end-hour').value = props.raw_end.split(':')[0];
                document.getElementById('end-min').value = props.raw_end.split(':')[1];
            }
            toggleCustomTime();
            viewModal.hide();
            scheduleModal.show();
        }

        function confirmAndSave() {
            const editId = document.getElementById('edit-id').value;
            const planInput = document.getElementById('plan-input').value.trim();

            if (!planInput) {
                alert("계획 및 메모를 입력해주세요.");
                document.getElementById('plan-input').focus();
                return;
            }

            if (editId) {
                if (confirm("이 일정을 수정하시겠습니까?")) saveSchedule();
            } else {
                saveSchedule();
            }
        }

        async function saveSchedule(mode = null) {
            const editId = document.getElementById('edit-id').value;
            const type = document.getElementById('type-select').value;
            const date = document.getElementById('date-input').value;
            const planNote = document.getElementById('plan-input').value;
            
            const formData = new FormData();
            formData.append('schedule_date', date);
            formData.append('schedule_type', type);
            formData.append('plan_note', planNote);

            if (editId) formData.append('id', editId);
            if (mode === 'overwrite') formData.append('mode', 'overwrite');

            // ETC일 경우만 직접 선택한 시간 전송, M/A/K는 서버에서 사용자 설정을 참조하여 처리하게 함
            if (type === 'ETC') {
                const sTime = document.getElementById('start-hour').value + ":" + document.getElementById('start-min').value + ":00";
                const eTime = document.getElementById('end-hour').value + ":" + document.getElementById('end-min').value + ":00";
                formData.append('start_time', sTime);
                formData.append('end_time', eTime);
            }

            try {
                const resp = await fetch('minjun_input.php', { method: 'POST', body: formData });
                const res = await resp.json();
                
                if (res.success) {
                    alert(res.message);
                    location.reload();
                } else if (res.error_type === 'DUPLICATE') {
                    const confirmMsg = `해당 날짜에 이미 일정이 존재합니다.\n\n[기존 일정]\n${res.existing_info}\n\n덮어쓰시겠습니까?`;
                    if(confirm(confirmMsg)) saveSchedule('overwrite');
                } else { 
                    alert(res.message); 
                }
            } catch (e) { 
                alert("서버 통신 중 오류가 발생했습니다."); 
            }
        }

        async function deleteSchedule() {
            if(!selectedEventId) return;
            if(!confirm("이 일정을 영구적으로 삭제하시겠습니까?")) return;

            const formData = new FormData();
            formData.append('id', selectedEventId);

            try {
                const resp = await fetch('delete_schedule.php', { method: 'POST', body: formData });
                const res = await resp.json();
                if(res.success) {
                    alert("삭제되었습니다.");
                    location.reload();
                } else { alert("삭제 실패: " + res.message); }
            } catch (e) { alert("삭제 처리 중 에러가 발생했습니다."); }
        }
    </script>
</body>
</html> 