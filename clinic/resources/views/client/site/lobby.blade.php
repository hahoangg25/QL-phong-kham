<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Màn hình hàng đợi - {{ config('clinic.name') }}</title>
  <link href="{{ asset('assets/img/favicon.png') }}" rel="icon">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Roboto:wght@400;500&display=swap&subset=vietnamese" rel="stylesheet">
  <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
  <style>
    :root { --bg: #071a2f; --panel: #0e2a47; --panel-2: #12355a; --accent: #3fa2ff; --gold: #ffc542; --text: #e8f1fb; --muted: #8fa9c6; }
    html, body { height: 100%; }
    body { background: radial-gradient(circle at top left, #0f3157, var(--bg) 60%); color: var(--text); font-family: "Roboto", sans-serif; overflow-x: hidden; }
    .lobby { min-height: 100vh; display: flex; flex-direction: column; padding: 20px 28px; }
    .lobby-header { display: flex; align-items: center; gap: 16px; margin-bottom: 18px; }
    .brand-mark { width: 54px; height: 54px; border-radius: 14px; background: linear-gradient(135deg, #1977cc, #3fbbc0); display: flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; }
    .brand h1 { font-family: "Montserrat"; font-weight: 800; font-size: 26px; margin: 0; color: #fff; }
    .brand small { color: var(--muted); font-size: 14px; }
    .clock { margin-left: auto; text-align: right; }
    .clock .time { font-family: "Montserrat"; font-weight: 700; font-size: 44px; line-height: 1; color: #fff; font-variant-numeric: tabular-nums; }
    .clock .date { color: var(--muted); font-size: 15px; text-transform: capitalize; }

    .announce { display: none; background: linear-gradient(90deg, #ffb703, #ffd166); color: #2b1b00; border-radius: 16px; padding: 16px 24px; margin-bottom: 18px; font-family: "Montserrat"; font-weight: 800; font-size: 34px; align-items: center; gap: 18px; box-shadow: 0 0 40px rgba(255, 197, 66, .45); animation: pulse 1.2s ease-in-out infinite alternate; }
    .announce.show { display: flex; }
    .announce .num { background: #2b1b00; color: var(--gold); border-radius: 12px; padding: 2px 18px; }
    @keyframes pulse { from { transform: scale(1); } to { transform: scale(1.015); } }

    .rooms { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 18px; flex: 1; align-content: start; }
    .room { background: linear-gradient(160deg, var(--panel-2), var(--panel)); border: 1px solid rgba(255,255,255,.06); border-radius: 18px; padding: 18px 20px; display: flex; flex-direction: column; transition: box-shadow .4s, border-color .4s; }
    .room.flash { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(255,197,66,.5), 0 0 40px rgba(255,197,66,.25); }
    .room-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .room-code { font-family: "Montserrat"; font-weight: 800; font-size: 26px; color: #fff; background: rgba(63,162,255,.18); border-radius: 10px; padding: 2px 14px; }
    .room-doctor { color: var(--muted); font-size: 15px; text-align: right; line-height: 1.3; }
    .current { text-align: center; padding: 14px 0 10px; }
    .current .label { text-transform: uppercase; letter-spacing: 2px; font-size: 13px; color: var(--muted); }
    .current .number { font-family: "Montserrat"; font-weight: 800; font-size: 96px; line-height: 1; color: var(--gold); text-shadow: 0 0 30px rgba(255,197,66,.35); }
    .current .number.idle { color: #4d6a8c; font-size: 60px; text-shadow: none; padding: 16px 0; }
    .current .name { font-size: 20px; color: #fff; min-height: 28px; }
    .next { border-top: 1px dashed rgba(255,255,255,.12); padding-top: 12px; margin-top: auto; }
    .next .label { font-size: 13px; color: var(--muted); display: flex; justify-content: space-between; }
    .chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; min-height: 40px; }
    .chip { background: rgba(255,255,255,.08); border-radius: 10px; padding: 6px 12px; font-family: "Montserrat"; font-weight: 700; font-size: 20px; color: #fff; }
    .chip.priority { background: rgba(255,197,66,.18); color: var(--gold); }
    .chip.priority::before { content: "\2605 "; font-size: 14px; }

    .empty { grid-column: 1 / -1; text-align: center; color: var(--muted); padding: 80px 0; font-size: 22px; }
    .lobby-footer { display: flex; align-items: center; gap: 14px; margin-top: 18px; color: var(--muted); font-size: 15px; }
    .ticker { flex: 1; overflow: hidden; white-space: nowrap; }
    .ticker span { display: inline-block; padding-left: 100%; animation: ticker 32s linear infinite; }
    @keyframes ticker { to { transform: translateX(-100%); } }
    .btn-sound { border-radius: 50px; }
    .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; background: #ffb703; margin-right: 6px; }
    .status-dot.live { background: #2ecc71; }
  </style>
</head>

<body>
  <div class="lobby">
    <header class="lobby-header">
      <div class="brand-mark"><i class="bi bi-heart-pulse-fill"></i></div>
      <div class="brand">
        <h1>{{ config('clinic.name') }}</h1>
        <small>Màn hình hàng đợi · Vui lòng chú ý số thứ tự và phòng khám của bạn</small>
      </div>
      <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">{{ now()->translatedFormat('l, d/m/Y') }}</div>
      </div>
    </header>

    <div class="announce" id="announce">
      <i class="bi bi-megaphone-fill"></i>
      <span>Mời bệnh nhân số <span class="num" id="announceNum">--</span> vào phòng <span class="num" id="announceRoom">--</span></span>
      <span class="ms-auto fs-4 fw-semibold" id="announceName"></span>
    </div>

    <main class="rooms" id="rooms">
      <div class="empty"><div class="spinner-border text-info mb-3"></div><div>Đang tải dữ liệu hàng đợi...</div></div>
    </main>

    <footer class="lobby-footer">
      <span><span class="status-dot" id="statusDot"></span><span id="statusText">Đang kết nối</span></span>
      <div class="ticker"><span>Quý khách có lịch hẹn vui lòng check-in tại quầy lễ tân bằng mã QR · Người cao tuổi, trẻ nhỏ được ưu tiên (★) · Đến trễ quá {{ setting('late_checkin_minutes') }} phút so với giờ hẹn sẽ được xếp cuối hàng đợi · Đặt lịch trực tuyến tại website hoặc hotline {{ config('clinic.hotline') }}</span></div>
      <button type="button" class="btn btn-outline-light btn-sm btn-sound" id="btnSound"><i class="bi bi-volume-mute me-1"></i>Bật âm thanh</button>
    </footer>
  </div>

  <script src="{{ asset('js/app.js') }}?v=1"></script>
  @include('common.partials.realtime')
  <script>
    (function () {
      const dataUrl = @json(route('lobby.data'));
      const roomsEl = document.getElementById('rooms');
      const lastCurrent = {};
      let firstLoad = true;
      let soundOn = false;
      let hideTimer = null;
      let audioCtx = null;
      let flash = { room: null, until: 0 };

      // Viền sáng phòng vừa gọi; áp lại sau mỗi lần vẽ lại danh sách
      function applyFlash() {
        const active = Date.now() < flash.until;
        document.querySelectorAll('.room').forEach((el) => el.classList.toggle('flash', active && el.dataset.room === flash.room));
      }

      // Đồng hồ
      const tick = () => { document.getElementById('clock').textContent = new Date().toLocaleTimeString('vi-VN', { hour12: false }); };
      tick(); setInterval(tick, 1000);

      // Bật âm thanh (trình duyệt yêu cầu thao tác người dùng trước khi phát giọng nói)
      document.getElementById('btnSound').addEventListener('click', function () {
        soundOn = !soundOn;
        this.innerHTML = soundOn ? '<i class="bi bi-volume-up me-1"></i>Tắt âm thanh' : '<i class="bi bi-volume-mute me-1"></i>Bật âm thanh';
        if (soundOn) {
          try { audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {}
          App.speak('Đã bật thông báo bằng giọng nói');
        }
      });

      function chime() {
        if (!audioCtx) return;
        [880, 660].forEach((freq, i) => {
          const o = audioCtx.createOscillator(), g = audioCtx.createGain();
          o.frequency.value = freq; o.connect(g); g.connect(audioCtx.destination);
          const t = audioCtx.currentTime + i * 0.35;
          g.gain.setValueAtTime(0.25, t); g.gain.exponentialRampToValueAtTime(0.001, t + 0.3);
          o.start(t); o.stop(t + 0.32);
        });
      }

      function announce(a) {
        if (!a || !a.number) return;
        document.getElementById('announceNum').textContent = a.number;
        document.getElementById('announceRoom').textContent = a.room || '';
        document.getElementById('announceName').textContent = a.name || '';
        const box = document.getElementById('announce');
        box.classList.add('show');
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => box.classList.remove('show'), 12000);

        flash = { room: String(a.room), until: Date.now() + 12000 };
        applyFlash();
        setTimeout(applyFlash, 12100);

        if (soundOn) {
          chime();
          setTimeout(() => App.speak(`Mời bệnh nhân số ${a.number} vào phòng ${String(a.room).split('').join(' ')}`), 800);
        }
      }

      function render(data) {
        if (data.date) document.getElementById('date').textContent = data.date;
        const rooms = data.rooms || [];

        if (!rooms.length) {
          roomsEl.innerHTML = '<div class="empty"><i class="bi bi-cup-hot d-block fs-1 mb-2"></i>Hiện chưa có bệnh nhân trong hàng đợi</div>';
        } else {
          roomsEl.innerHTML = rooms.map((r) => `
            <section class="room" data-room="${App.escape(r.room)}">
              <div class="room-head">
                <span class="room-code">${App.escape(r.room)}</span>
                <span class="room-doctor">${App.escape(r.doctor)}</span>
              </div>
              <div class="current">
                <div class="label">Đang khám</div>
                ${r.current
                  ? `<div class="number">${r.current.number}</div><div class="name">${App.escape(r.current.name)}</div>`
                  : '<div class="number idle">--</div><div class="name"></div>'}
              </div>
              <div class="next">
                <div class="label"><span>Số tiếp theo</span><span>Đang chờ: ${r.waiting_count}</span></div>
                <div class="chips">
                  ${(r.waiting || []).map((w) => `<span class="chip ${w.priority ? 'priority' : ''}">${w.number}</span>`).join('') || '<span class="text-secondary small">Không có</span>'}
                </div>
              </div>
            </section>`).join('');
          applyFlash();
        }

        // Không có WebSocket: tự phát hiện số đang khám thay đổi giữa hai lần làm mới để thông báo
        rooms.forEach((r) => {
          const num = r.current ? r.current.number : null;
          if (!firstLoad && !App.realtime && num && lastCurrent[r.doctor_id] !== num) {
            announce({ number: num, room: r.room, name: r.current.name });
          }
          lastCurrent[r.doctor_id] = num;
        });
        firstLoad = false;
      }

      function setStatus(ok) {
        const live = ok && App.realtime;
        document.getElementById('statusDot').classList.toggle('live', !!ok);
        document.getElementById('statusText').textContent = ok
          ? (live ? 'Kết nối thời gian thực' : 'Tự động cập nhật mỗi 5 giây')
          : 'Mất kết nối, đang thử lại...';
      }

      App.onQueueUpdate((data) => { if (data && data.announce) announce(data.announce); });
      App.poll(() => App.fetchJson(dataUrl).then((d) => { render(d); setStatus(true); }).catch(() => setStatus(false)), 5000);
    })();
  </script>
</body>

</html>
