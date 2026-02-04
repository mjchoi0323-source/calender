<?php
// 1. 세션 및 로그인 체크
session_start();
if (!isset($_SESSION['user_idx'])) {
    header("Location: login.php");
    exit;
}
$user_idx = $_SESSION['user_idx'];
$user_name = $_SESSION['user_name'] ?? '사용자'; 

// 2. DB 연결
try {
    require_once 'db_connect.php';
} catch (Exception $e) {
    die("DB 연결 실패: " . $e->getMessage());
}

// 3. 네비게이션용 연/월 설정
$target_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$target_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');

$prev_date = date('Y-m', strtotime("$target_year-$target_month-01 -1 month"));
$next_date = date('Y-m', strtotime("$target_year-$target_month-01 +1 month"));
list($prev_y, $prev_m) = explode('-', $prev_date);
list($next_y, $next_m) = explode('-', $next_date);

// 4. 사용자의 커스텀 시간 설정 로드
$user_times = [
    'M' => ['start' => '07:00', 'end' => '15:30'],
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

// 5. 현재 선택된 달의 일정 가져오기
$startDateStr = "$target_year-" . str_pad($target_month, 2, '0', STR_PAD_LEFT) . "-01";
$endDateStr = date('Y-m-t', strtotime($startDateStr));

$sql = "SELECT id, schedule_date, schedule_type, start_time, end_time, plan_note 
        FROM user_schedules 
        WHERE user_idx = :user_idx AND schedule_date BETWEEN :s AND :e
        ORDER BY schedule_date ASC, start_time ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':user_idx' => $user_idx, ':s' => $startDateStr, ':e' => $endDateStr]);

$events = [];
$mobile_events_group = []; 
while ($row = $stmt->fetch()) {
    $type = $row['schedule_type'];
    $color = '#607d8b';
    if ($type === 'M') $color = '#4caf50';
    else if ($type === 'K') $color = '#ff9800';
    else if ($type === 'A') $color = '#2196f3';
    else if ($type === 'OFF') $color = '#f44336';

    $eventData = [
        'id' => $row['id'],
        'title' => "[" . $type . "] " . $row['plan_note'],
        'start' => $row['schedule_date'],
        'backgroundColor' => $color,
        'borderColor' => $color,
        'extendedProps' => [
            'type' => $type, 
            'note' => $row['plan_note'],
            'raw_date' => $row['schedule_date'],
            'raw_start' => $row['start_time'] ? substr($row['start_time'], 0, 5) : '',
            'raw_end' => $row['end_time'] ? substr($row['end_time'], 0, 5) : ''
        ]
    ];
    $events[] = $eventData;
    $mobile_events_group[$row['schedule_date']][] = $eventData;
}

$fc_summary_events = [];
foreach ($mobile_events_group as $date => $list) {
    $count = count($list);
    $displayTitle = ($count > 1) ? "일정이 있습니다({$count}개)" : "일정이 있습니다";
    $fc_summary_events[] = [
        'title' => $displayTitle, 'start' => $date, 'allDay' => true,
        'backgroundColor' => '#4a90e2', 'borderColor' => '#4a90e2'
    ];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>업무 스케줄러 Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root { --primary: #4a90e2; }
        body { font-family: 'Pretendard', sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .user-header-table { width: 100%; max-width: 1000px; margin: 0 auto; border-collapse: collapse; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .user-header-table td { border: 1px solid #000; padding: 12px; font-size: 14px; }
        .user-header-table a { text-decoration: none; color: #333; font-weight: bold; }
        #calendar-container { max-width: 1000px; margin: 20px auto; background: white; padding: 20px; border-radius: 15px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .mobile-list-container { display: none; width: 100%; max-width: 1000px; margin: 0 auto; background: #fff; border: 1px solid #000; border-top: none; }
        .mobile-nav { display: flex; justify-content: space-between; border-bottom: 1px solid #000; padding: 10px 15px; font-weight: bold; background: #fdfdfd; align-items: center; }
        .nav-buttons span { cursor: pointer; padding: 5px 10px; font-size: 1.3rem; user-select: none; }
        .nav-today { cursor: pointer; border: 1px solid #ccc; padding: 3px 10px; border-radius: 5px; font-size: 0.9rem; background: #fff; margin-left: 10px; }
        .mobile-month-title { text-align: center; border-bottom: 1px solid #000; padding: 12px; font-size: 1.25rem; font-weight: bold; background: #fff; }
        .mobile-row { display: flex; border-bottom: 1px solid #000; min-height: 60px; align-items: stretch; }
        .date-cell { width: 35%; padding: 10px; border-right: 1px solid #000; cursor: pointer; display: flex; flex-direction: column; justify-content: center; align-items: center; background-color: #fafafa; }
        .day-num { font-size: 1.2rem; font-weight: bold; }
        .day-name { font-size: 0.85rem; color: #666; }
        .content-cell-wrapper { width: 65%; display: flex; flex-direction: column; justify-content: center; }
        .event-item-mobile { padding: 12px 15px; cursor: pointer; color: var(--primary); font-weight: bold; font-size: 0.95rem; }
        .empty-cell { padding: 15px; color: #bbb; font-size: 0.9rem; font-style: italic; cursor: pointer; text-align: center; }
        /* 일별 목록 내 시간 스타일 */
        .list-time-badge { font-size: 0.8rem; color: #666; background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-weight: normal; margin-top: 4px; display: inline-block; }
        .view-label { font-weight: bold; color: #555; font-size: 14px; margin-bottom: 4px; display: block; }
        .view-value { padding: 12px; background: #f8f9fa; border: 1px solid #eee; border-radius: 10px; min-height: 45px; display: flex; align-items: center; }
        @media (max-width: 768px) {
            body { padding: 10px; }
            #calendar-container { display: none; }
            .mobile-list-container { display: block; }
        }
    </style>
</head>
<body>

    <table class="user-header-table">
        <tr>
            <td style="width:60%;"><strong><?php echo htmlspecialchars($user_name); ?></strong> 님 환영합니다</td>
            <td style="width:40%; text-align:center;">
                <a href="profile_edit.php">정보수정</a> | <a href="logout.php">로그아웃</a>
            </td>
        </tr>
    </table>

    <div class="mobile-list-container">
        <div class="mobile-nav">
            <div class="nav-buttons">
                <span onclick="moveMonth('<?php echo $prev_y; ?>', '<?php echo $prev_m; ?>')">&lt;</span>
                <span onclick="moveMonth('<?php echo $next_y; ?>', '<?php echo $next_m; ?>')">&gt;</span>
                <span class="nav-today" onclick="moveMonth('<?php echo date('Y'); ?>', '<?php echo date('n'); ?>')">오늘</span>
            </div>
            <div style="font-size: 0.9rem; color: #666;">월간 리스트</div>
        </div>
        <div class="mobile-month-title"><?php echo $target_year; ?>년 <?php echo $target_month; ?>월</div>
        <?php
        $start_day = new DateTime("$target_year-$target_month-01");
        $end_day = new DateTime($start_day->format('Y-m-t'));
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start_day, $interval, $end_day->modify('+1 day'));
        foreach ($period as $date) {
            $dStr = $date->format('Y-m-d');
            $dayEvents = $mobile_events_group[$dStr] ?? [];
            $w = $date->format('w');
            $colorName = ($w == 0) ? 'red' : (($w == 6) ? 'blue' : 'black');
            ?>
            <div class="mobile-row">
                <div class="date-cell" onclick="showDailyListModal('<?php echo $dStr; ?>')">
                    <span class="day-num" style="color: <?php echo $colorName; ?>;"><?php echo $date->format('j'); ?></span>
                    <span class="day-name"><?php echo ["일","월","화","수","목","금","토"][$w]; ?>요일</span>
                </div>
                <div class="content-cell-wrapper">
                    <?php if(!empty($dayEvents)): 
                        $mCount = count($dayEvents);
                        $mTitle = ($mCount > 1) ? "일정이 있습니다({$mCount}개)" : "일정이 있습니다";
                    ?>
                        <div class="event-item-mobile" onclick="showDailyListModal('<?php echo $dStr; ?>')"><?php echo $mTitle; ?></div>
                    <?php else: ?>
                        <div class="empty-cell" onclick="openAddModalByDate('<?php echo $dStr; ?>')">일정이 없습니다.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php } ?>
    </div>

    <div id="calendar-container"><div id="calendar"></div></div>

    <div class="modal fade" id="dailyListModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold"><span id="list-modal-date"></span> 일정 목록</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="daily-list-content" class="list-group list-group-flush"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary w-100" id="list-add-btn">새 일정 추가</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitle">스케줄 등록</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit-id">
                    <div class="mb-3"><label class="form-label fw-bold">날짜</label><input type="date" id="date-input" class="form-control"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">타입 선택</label>
                        <select id="type-select" class="form-select" onchange="toggleCustomTime()">
                            <option value="M">M (오전)</option>
                            <option value="A">A (통상)</option>
                            <option value="K">K (오후)</option>
                            <option value="OFF">Day Off (휴무)</option>
                            <option value="ETC">기타 (직접 입력)</option>
                        </select>
                    </div>
                    <div id="custom-time-container" class="mb-3" style="display:none;">
                        <label class="form-label fw-bold">시간 설정</label>
                        <div class="d-flex align-items-center gap-2">
                            <select id="start-hour" class="form-select"></select> : <select id="start-min" class="form-select"></select>
                            <span>~</span>
                            <select id="end-hour" class="form-select"></select> : <select id="end-min" class="form-select"></select>
                        </div>
                    </div>
                    <div class="mb-3"><label class="form-label fw-bold">계획 및 메모</label><input type="text" id="plan-input" class="form-control" placeholder="계획을 입력해 주세요."></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                    <button type="button" class="btn btn-primary" onclick="confirmAndSave()">저장하기</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title">상세 일정 정보</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="view-label">날짜</label><div class="view-value" id="view-date"></div></div>
                    <div class="mb-3"><label class="view-label">근무 타입</label><div class="view-value" id="view-type"></div></div>
                    <div class="mb-3"><label class="view-label">근무 시간</label><div class="view-value" id="view-time"></div></div>
                    <div class="mb-3"><label class="view-label">메모 내용</label><div class="view-value" id="view-note" style="min-height:80px; align-items:flex-start;"></div></div>
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
        let scheduleModal, viewModal, dailyListModal;
        let selectedEventId = null; 
        const userTimeSettings = <?php echo json_encode($user_times); ?>;
        const mobileEventsGroup = <?php echo json_encode($mobile_events_group); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
            viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
            dailyListModal = new bootstrap.Modal(document.getElementById('dailyListModal'));
            initTimeOptions();
            updateSelectLabels();
            calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                initialView: 'dayGridMonth',
                initialDate: '<?php echo $startDateStr; ?>',
                locale: 'ko',
                headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
                events: <?php echo json_encode($fc_summary_events); ?>,
                dateClick: (info) => showDailyListModal(info.dateStr.split('T')[0]),
                eventClick: (info) => showDailyListModal(info.event.startStr)
            });
            calendar.render();
        });

        function showDailyListModal(dateStr) {
            const listContainer = document.getElementById('daily-list-content');
            const dateTitle = document.getElementById('list-modal-date');
            const addBtn = document.getElementById('list-add-btn');
            dateTitle.innerText = dateStr;
            listContainer.innerHTML = '';
            const dayEvents = mobileEventsGroup[dateStr] || [];
            
            if(dayEvents.length > 0) {
                dayEvents.forEach(ev => {
                    const btn = document.createElement('button');
                    btn.className = 'list-group-item list-group-item-action py-3 d-flex flex-column align-items-start';
                    
                    // 시간 텍스트 생성 (OFF면 "휴무", 그외엔 "시작~종료")
                    let timeText = "";
                    if(ev.extendedProps.type === 'OFF') {
                        timeText = "휴무";
                    } else if(ev.extendedProps.raw_start && ev.extendedProps.raw_end) {
                        timeText = `${ev.extendedProps.raw_start} ~ ${ev.extendedProps.raw_end}`;
                    } else {
                        // DB에서 설정한 기본 시간 가져오기
                        const defaultTime = userTimeSettings[ev.extendedProps.type];
                        if(defaultTime) timeText = `${defaultTime.start} ~ ${defaultTime.end}`;
                        else timeText = "시간 정보 없음";
                    }

                    btn.innerHTML = `
                        <div class="fw-bold">${ev.title}</div>
                        <div class="list-time-badge">${timeText}</div>
                    `;
                    btn.onclick = () => { dailyListModal.hide(); showEventDetailById(ev.id); };
                    listContainer.appendChild(btn);
                });
            } else {
                listContainer.innerHTML = '<div class="p-4 text-center text-muted">일정이 없습니다.</div>';
            }
            addBtn.onclick = () => { dailyListModal.hide(); openAddModalByDate(dateStr); };
            dailyListModal.show();
        }

        function moveMonth(y, m) { location.href = `?y=${y}&m=${m}`; }
        function openAddModalByDate(dateStr) { resetModal(); document.getElementById('date-input').value = dateStr; scheduleModal.show(); }
        
        function showEventDetailById(id) {
            const allEvents = <?php echo json_encode($events); ?>;
            const target = allEvents.find(e => e.id == id);
            if(target) {
                selectedEventId = target.id;
                document.getElementById('view-date').innerText = target.extendedProps.raw_date;
                document.getElementById('view-type').innerText = target.extendedProps.type;
                document.getElementById('view-note').innerText = target.extendedProps.note || "(메모 없음)";
                
                let timeStr = "";
                if(target.extendedProps.type === 'OFF') timeStr = "휴무";
                else if(target.extendedProps.raw_start) timeStr = `${target.extendedProps.raw_start} ~ ${target.extendedProps.raw_end}`;
                else {
                    const def = userTimeSettings[target.extendedProps.type];
                    timeStr = def ? `${def.start} ~ ${def.end}` : "정보 없음";
                }
                document.getElementById('view-time').innerText = timeStr;
                viewModal.show();
            }
        }

        async function saveSchedule(mode = null) {
            const planNote = document.getElementById('plan-input').value.trim();
            if (!planNote) { alert("메모를 입력해주세요."); return; }
            const formData = new FormData();
            formData.append('schedule_date', document.getElementById('date-input').value);
            formData.append('schedule_type', document.getElementById('type-select').value);
            formData.append('plan_note', planNote);
            if (document.getElementById('edit-id').value) formData.append('id', document.getElementById('edit-id').value);
            if (mode === 'overwrite') formData.append('mode', 'overwrite');
            if(document.getElementById('type-select').value === 'ETC') {
                formData.append('start_time', document.getElementById('start-hour').value + ":" + document.getElementById('start-min').value);
                formData.append('end_time', document.getElementById('end-hour').value + ":" + document.getElementById('end-min').value);
            }
            try {
                const resp = await fetch('minjun_input.php', { method: 'POST', body: formData });
                const res = await resp.json();
                if (res.success) { alert(res.message); location.reload(); } 
                else if (res.error_type === 'DUPLICATE') {
                    if(confirm(`겹치는 일정이 있습니다:\n${res.existing_info}\n\n기존 일정을 지우고 덮어쓰시겠습니까?`)) saveSchedule('overwrite');
                } else alert(res.message);
            } catch (e) { alert("통신 중 오류가 발생했습니다."); }
        }

        function confirmAndSave() { saveSchedule(); }
        async function deleteSchedule() {
            if(!selectedEventId || !confirm("정말 삭제하시겠습니까?")) return;
            const fd = new FormData(); fd.append('id', selectedEventId);
            const resp = await fetch('delete_schedule.php', { method: 'POST', body: fd });
            const res = await resp.json();
            if(res.success) { alert("삭제되었습니다."); location.reload(); } else alert(res.message);
        }

        function initTimeOptions() {
            const hSelects = [document.getElementById('start-hour'), document.getElementById('end-hour')];
            const mSelects = [document.getElementById('start-min'), document.getElementById('end-min')];
            hSelects.forEach(s => { for(let i=0; i<24; i++) s.add(new Option(i.toString().padStart(2,'0'), i.toString().padStart(2,'0'))); });
            mSelects.forEach(s => { for(let i=0; i<60; i+=5) s.add(new Option(i.toString().padStart(2,'0'), i.toString().padStart(2,'0'))); });
        }
        function toggleCustomTime() { document.getElementById('custom-time-container').style.display = (document.getElementById('type-select').value === 'ETC') ? 'block' : 'none'; }
        function updateSelectLabels() {
            const select = document.getElementById('type-select');
            for(let opt of select.options) {
                if(userTimeSettings[opt.value]) opt.text = `${opt.value} | ${userTimeSettings[opt.value].start} - ${userTimeSettings[opt.value].end}`;
            }
        }
        function resetModal() {
            document.getElementById('modalTitle').innerText = "새 스케줄 등록";
            document.getElementById('edit-id').value = ""; document.getElementById('plan-input').value = "";
            document.getElementById('type-select').value = "M"; toggleCustomTime();
        }
        function openEditModal() {
            const allEvents = <?php echo json_encode($events); ?>;
            const target = allEvents.find(e => e.id == selectedEventId);
            if(!target) return;
            document.getElementById('modalTitle').innerText = "스케줄 수정";
            document.getElementById('edit-id').value = selectedEventId;
            document.getElementById('date-input').value = target.extendedProps.raw_date;
            document.getElementById('type-select').value = target.extendedProps.type;
            document.getElementById('plan-input').value = target.extendedProps.note;
            if(target.extendedProps.type === 'ETC' && target.extendedProps.raw_start) {
                document.getElementById('start-hour').value = target.extendedProps.raw_start.split(':')[0];
                document.getElementById('start-min').value = target.extendedProps.raw_start.split(':')[1];
                document.getElementById('end-hour').value = target.extendedProps.raw_end.split(':')[0];
                document.getElementById('end-min').value = target.extendedProps.raw_end.split(':')[1];
            }
            toggleCustomTime(); viewModal.hide(); scheduleModal.show();
        }
    </script>
</body>
</html>