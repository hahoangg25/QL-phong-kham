{{--
  Bộ chọn chuyên khoa -> bác sĩ -> ngày -> khung giờ cho lễ tân (BN-07 với ?staff=1: được đặt sát giờ).
  Biến: $doctors (mảng: id, specialty_id, name, specialty, room, fee, avatar),
        $specialties (Collection|null - null khi cố định chuyên khoa), $selectedDoctorId (int|null), $excludeSlotId (int|null).
  Ghi kết quả vào input ẩn name="time_slot_id".
--}}
@php
  $selectedDoctorId = $selectedDoctorId ?? null;
  $excludeSlotId = $excludeSlotId ?? null;
@endphp

@push('styles')
  <style>
    .doctor-option { border: 1px solid #e3ebf6; border-radius: 10px; padding: 10px; cursor: pointer; transition: .2s; height: 100%; background: #fff; }
    .doctor-option:hover { border-color: var(--accent); }
    .doctor-option.active { border-color: var(--accent); background: var(--accent-soft); box-shadow: 0 0 0 3px rgba(25,119,204,.12); }
    .doctor-option img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
    .date-strip { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; }
    .date-chip { min-width: 70px; border: 1px solid #d6e4f5; border-radius: 10px; background: #fff; padding: 6px; text-align: center; cursor: pointer; }
    .date-chip .dow { font-size: 12px; color: #6c757d; }
    .date-chip .day { font-weight: 700; color: #112344; }
    .date-chip .cnt { font-size: 11px; color: #18864b; }
    .date-chip.active { background: var(--accent); border-color: var(--accent); }
    .date-chip.active .dow, .date-chip.active .day, .date-chip.active .cnt { color: #fff; }
    .date-chip.off { opacity: .45; }
    .slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(92px, 1fr)); gap: 8px; }
    .slot-btn { border: 1px solid #d6e4f5; background: #fff; border-radius: 8px; padding: 6px; text-align: center; line-height: 1.2; }
    .slot-btn .time { font-weight: 600; color: #112344; }
    .slot-btn .left { font-size: 11.5px; color: #18864b; }
    .slot-btn:hover:not(:disabled) { border-color: var(--accent); }
    .slot-btn.active { background: var(--accent); border-color: var(--accent); }
    .slot-btn.active .time, .slot-btn.active .left { color: #fff; }
    .slot-btn:disabled { background: #f3f4f6; border-color: #eee; }
    .slot-btn:disabled .time { color: #adb5bd; text-decoration: line-through; }
    .slot-btn:disabled .left { color: #adb5bd; }
  </style>
@endpush

<div id="slotPicker">
  <input type="hidden" name="time_slot_id" id="timeSlotId" value="{{ old('time_slot_id') }}">

  @if ($specialties)
    <div class="mb-3">
      <label class="form-label required">Chuyên khoa</label>
      <select class="form-select" id="spSpecialty">
        <option value="">-- Chọn chuyên khoa --</option>
        @foreach ($specialties as $s)
          <option value="{{ $s->id }}">{{ $s->name }}</option>
        @endforeach
      </select>
    </div>
  @endif

  <label class="form-label required">Bác sĩ</label>
  <div class="row g-2 mb-3" id="spDoctors"></div>

  <div id="spDateBox" style="display:none">
    <label class="form-label required">Ngày khám</label>
    <div class="date-strip mb-3" id="spDates"></div>
  </div>

  <div id="spSlotBox" style="display:none">
    <label class="form-label required">Khung giờ</label>
    <div id="spSlots"></div>
  </div>

  <div class="alert alert-primary py-2 mt-3 mb-0" id="spSummary" style="display:none"></div>
</div>

@push('scripts')
  <script>
    (function () {
      const slotsUrl = @json(route('slots.index'));
      const doctors = @json($doctors);
      const excludeSlot = @json($excludeSlotId);
      const state = { specialty: null, doctor: @json($selectedDoctorId), date: null, slot: null };
      const el = (id) => document.getElementById(id);
      const DOW = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
      const pad = (n) => String(n).padStart(2, '0');
      const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

      function renderDoctors() {
        const list = doctors.filter((d) => !el('spSpecialty') || !state.specialty || String(d.specialty_id) === String(state.specialty));
        if (el('spSpecialty') && !state.specialty) {
          el('spDoctors').innerHTML = '<div class="col-12 text-muted small">Chọn chuyên khoa để xem danh sách bác sĩ.</div>';
          return;
        }
        el('spDoctors').innerHTML = list.length ? list.map((d) => `
          <div class="col-md-6 col-xl-4">
            <div class="doctor-option d-flex gap-2 align-items-center ${String(d.id) === String(state.doctor) ? 'active' : ''}" data-id="${d.id}">
              <img src="${App.escape(d.avatar)}" alt="">
              <div class="small"><div class="fw-semibold">${App.escape(d.name)}</div>
                <div class="text-muted">${App.escape(d.specialty)}${d.room ? ' · P.' + App.escape(d.room) : ''} · ${App.money(d.fee)}</div></div>
            </div>
          </div>`).join('') : '<div class="col-12 text-muted small">Chuyên khoa chưa có bác sĩ.</div>';
        el('spDoctors').querySelectorAll('.doctor-option').forEach((o) => o.addEventListener('click', () => {
          state.doctor = o.dataset.id;
          state.date = null;
          setSlot(null);
          renderDoctors();
          loadDates();
        }));
      }

      async function loadDates() {
        el('spDateBox').style.display = '';
        el('spSlotBox').style.display = 'none';
        el('spDates').innerHTML = '<span class="text-muted small">Đang tải...</span>';
        let days;
        try {
          days = (await App.fetchJson(`${slotsUrl}?doctor_id=${state.doctor}&from=${iso(new Date())}&days=14&staff=1`)).days;
        } catch (e) {
          // Dự phòng: tự tạo 14 ngày tới nếu API dải ngày không khả dụng
          days = [...Array(14)].map((_, i) => { const d = new Date(); d.setDate(d.getDate() + i);
            return { date: iso(d), dow: DOW[d.getDay()], day: `${pad(d.getDate())}/${pad(d.getMonth() + 1)}`, available: null }; });
        }
        el('spDates').innerHTML = days.map((d) => `
          <div class="date-chip ${d.available === 0 ? 'off' : ''}" data-date="${d.date}">
            <div class="dow">${App.escape(d.dow)}</div><div class="day">${App.escape(d.day)}</div>
            <div class="cnt">${d.available === null ? '' : (d.available ? d.available + ' giờ trống' : 'Hết chỗ')}</div>
          </div>`).join('');
        el('spDates').querySelectorAll('.date-chip').forEach((c) => c.addEventListener('click', () => {
          el('spDates').querySelectorAll('.date-chip').forEach((x) => x.classList.toggle('active', x === c));
          state.date = c.dataset.date;
          setSlot(null);
          loadSlots();
        }));
        const first = el('spDates').querySelector('.date-chip:not(.off)');
        if (first) first.click();
      }

      async function loadSlots() {
        el('spSlotBox').style.display = '';
        el('spSlots').innerHTML = '<span class="text-muted small">Đang tải...</span>';
        try {
          const data = await App.fetchJson(`${slotsUrl}?doctor_id=${state.doctor}&date=${state.date}&staff=1`);
          const slots = data.slots.filter((s) => String(s.id) !== String(excludeSlot));
          if (!slots.length) {
            el('spSlots').innerHTML = '<div class="text-muted small">Bác sĩ không có lịch làm việc ngày này.</div>';
            return;
          }
          const groups = {};
          slots.forEach((s) => (groups[s.period] = groups[s.period] || []).push(s));
          el('spSlots').innerHTML = Object.entries(groups).map(([period, list]) => `
            <div class="small text-muted fw-semibold mb-1 mt-2">${App.escape(period)}</div>
            <div class="slot-grid">${list.map((s) => `
              <button type="button" class="slot-btn" data-id="${s.id}" data-time="${s.time}" ${s.bookable ? '' : 'disabled'}>
                <div class="time">${s.time}</div><div class="left">${s.available > 0 ? 'Còn ' + s.available + '/' + s.capacity : 'Hết chỗ'}</div>
              </button>`).join('')}</div>`).join('');
          el('spSlots').querySelectorAll('.slot-btn:not(:disabled)').forEach((b) => b.addEventListener('click', () => {
            el('spSlots').querySelectorAll('.slot-btn').forEach((x) => x.classList.toggle('active', x === b));
            setSlot({ id: b.dataset.id, time: b.dataset.time });
          }));
        } catch (e) {
          el('spSlots').innerHTML = `<div class="text-danger small">${App.escape(e.message)}</div>`;
        }
      }

      function setSlot(slot) {
        state.slot = slot;
        el('timeSlotId').value = slot ? slot.id : '';
        const d = doctors.find((x) => String(x.id) === String(state.doctor));
        el('spSummary').style.display = slot ? '' : 'none';
        if (slot && d) {
          const [y, m, day] = state.date.split('-');
          el('spSummary').innerHTML = `<i class="bi bi-calendar-check me-1"></i>Đã chọn: <strong>${slot.time}</strong> ngày <strong>${day}/${m}/${y}</strong> - ${App.escape(d.name)} (${App.escape(d.specialty)})`;
        }
      }

      if (el('spSpecialty')) {
        el('spSpecialty').addEventListener('change', () => {
          state.specialty = el('spSpecialty').value || null;
          state.doctor = null;
          setSlot(null);
          el('spDateBox').style.display = 'none';
          el('spSlotBox').style.display = 'none';
          renderDoctors();
        });
        if (state.doctor) {
          const d = doctors.find((x) => String(x.id) === String(state.doctor));
          if (d) { el('spSpecialty').value = d.specialty_id; state.specialty = String(d.specialty_id); }
        }
      }
      renderDoctors();
      if (state.doctor) loadDates();
    })();
  </script>
@endpush
