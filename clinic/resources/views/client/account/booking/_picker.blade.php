{{--
  Bộ chọn chuyên khoa -> bác sĩ -> ngày -> khung giờ (BN-07), dùng chung cho đặt lịch và đổi lịch.
  Tham số: $mode ('booking'|'reschedule'), $specialties (booking), $doctorsJson, $prefill, $fixedSpecialty (reschedule), $excludeSlotId
  Phát sự kiện "booking:change" trên document và tự cập nhật các phần tử [data-sum="specialty|doctor|date|time|fee"].
--}}
@php
  $mode = $mode ?? 'booking';
  $prefill = $prefill ?? [];
@endphp

<div id="bookingPicker">
  @if ($mode === 'booking')
    <div class="card card-soft mb-3" id="step-specialty">
      <div class="card-header"><span class="badge bg-primary rounded-pill me-2">1</span>Chọn chuyên khoa</div>
      <div class="card-body">
        <div class="p-3 rounded mb-3" style="background:#f4f8ff">
          <label for="symptomQuery" class="form-label small fw-semibold mb-1"><i class="bi bi-magic me-1"></i>Chưa biết nên khám chuyên khoa nào? Nhập triệu chứng để được gợi ý</label>
          <div class="input-group">
            <input type="text" id="symptomQuery" class="form-control" placeholder="vd: đau họng, sổ mũi, sốt nhẹ">
            <button class="btn btn-outline-primary" type="button" id="symptomBtn"><i class="bi bi-search me-1"></i>Gợi ý</button>
          </div>
          <div id="symptomResult" class="mt-2 small"></div>
        </div>
        <div class="row g-2" id="specialtyList">
          @foreach ($specialties as $s)
            <div class="col-6 col-md-4">
              <div class="doctor-option specialty-option text-center py-3" data-id="{{ $s->id }}" data-name="{{ $s->name }}" role="button" tabindex="0">
                <i class="fa-solid {{ $s->icon ?: 'fa-stethoscope' }} fs-3 text-primary mb-2 d-block"></i>
                <div class="fw-semibold small">{{ $s->name }}</div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  <div class="card card-soft mb-3 {{ $mode === 'booking' ? 'd-none' : '' }}" id="step-doctor">
    <div class="card-header"><span class="badge bg-primary rounded-pill me-2">{{ $mode === 'booking' ? 2 : 1 }}</span>Chọn bác sĩ
      @if ($mode === 'reschedule')<small class="text-muted fw-normal ms-1">({{ $fixedSpecialty['name'] }})</small>@endif
    </div>
    <div class="card-body">
      <div class="row g-2" id="doctorList"></div>
    </div>
  </div>

  <div class="card card-soft mb-3 d-none" id="step-time">
    <div class="card-header"><span class="badge bg-primary rounded-pill me-2">{{ $mode === 'booking' ? 3 : 2 }}</span>Chọn ngày và giờ khám</div>
    <div class="card-body">
      <div class="date-strip mb-3" id="dateStrip"></div>
      <div id="slotArea"></div>
    </div>
  </div>

  <input type="hidden" name="time_slot_id" id="timeSlotInput" value="">
</div>

@php
  $pickerConfig = [
      'mode' => $mode,
      'doctors' => $doctorsJson,
      'prefill' => $prefill,
      'fixedSpecialty' => $fixedSpecialty ?? null,
      'excludeSlotId' => $excludeSlotId ?? null,
      'slotsUrl' => route('slots.index'),
      'suggestUrl' => route('symptoms.suggest'),
      'today' => today()->toDateString(),
      'days' => 14,
  ];
@endphp
<script type="application/json" id="bookingPickerConfig">@json($pickerConfig)</script>

@push('scripts')
  <script>
    (function () {
      const root = document.getElementById('bookingPicker');
      if (!root) return;
      const cfg = JSON.parse(document.getElementById('bookingPickerConfig').textContent);
      const $ = (id) => document.getElementById(id);
      const spinner = '<div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Đang tải...</div>';
      const state = { specialtyId: null, specialtyName: '', doctorId: null, doctorName: '', fee: null, date: null, dateLabel: '', slotId: null, time: '' };

      function emit() {
        const map = {
          specialty: state.specialtyName,
          doctor: state.doctorName,
          date: state.dateLabel,
          time: state.time,
          fee: state.fee !== null ? App.money(state.fee) : '',
        };
        document.querySelectorAll('[data-sum]').forEach((el) => { el.textContent = map[el.dataset.sum] || '—'; });
        const done = { 1: !!state.specialtyId, 2: !!state.doctorId, 3: !!state.slotId };
        let activeSet = false;
        document.querySelectorAll('.step-indicator .step[data-step]').forEach((el) => {
          const n = Number(el.dataset.step);
          const isDone = !!done[n];
          el.classList.toggle('done', isDone);
          el.classList.toggle('active', !isDone && !activeSet);
          if (!isDone) activeSet = true;
        });
        document.dispatchEvent(new CustomEvent('booking:change', { detail: Object.assign({}, state) }));
      }

      function scrollTo(id) {
        const el = $(id);
        if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth' });
      }

      function clearSlot() {
        state.slotId = null;
        state.time = '';
        $('timeSlotInput').value = '';
      }

      // ---------------- Bước 1: chuyên khoa ----------------
      function selectSpecialty(id, name, userAction = true) {
        state.specialtyId = Number(id);
        state.specialtyName = name;
        state.doctorId = null;
        state.doctorName = '';
        state.fee = null;
        state.date = null;
        state.dateLabel = '';
        clearSlot();
        document.querySelectorAll('.specialty-option').forEach((el) => el.classList.toggle('active', Number(el.dataset.id) === state.specialtyId));
        renderDoctors();
        $('step-doctor').classList.remove('d-none');
        $('step-time').classList.add('d-none');
        emit();
        if (userAction) scrollTo('step-doctor');
      }

      document.querySelectorAll('.specialty-option').forEach((el) => {
        el.addEventListener('click', () => selectSpecialty(el.dataset.id, el.dataset.name));
        el.addEventListener('keydown', (e) => { if (e.key === 'Enter') selectSpecialty(el.dataset.id, el.dataset.name); });
      });

      // BN-20: gợi ý chuyên khoa từ triệu chứng
      async function suggest() {
        const q = $('symptomQuery').value.trim();
        const box = $('symptomResult');
        if (q.length < 2) { box.innerHTML = '<span class="text-muted">Nhập ít nhất 2 ký tự.</span>'; return; }
        box.innerHTML = '<span class="text-muted">Đang phân tích...</span>';
        try {
          const data = await App.fetchJson(cfg.suggestUrl + '?' + new URLSearchParams({ q }));
          if (!data.suggestions.length) {
            box.innerHTML = '<span class="text-muted">Chưa tìm được chuyên khoa phù hợp. Bạn có thể chọn <strong>Nội tổng quát</strong> để được bác sĩ tư vấn.</span>';
            return;
          }
          box.innerHTML = '<div class="mb-1 text-muted">Chuyên khoa gợi ý (bấm để chọn):</div>' + data.suggestions.map((s, i) =>
            `<button type="button" class="btn btn-sm ${i === 0 ? 'btn-primary' : 'btn-outline-primary'} me-2 mb-1 suggest-pick" data-id="${s.id}" data-name="${App.escape(s.name)}">
              ${App.escape(s.name)} <small class="opacity-75">(${App.escape(s.matched.slice(0, 3).join(', '))})</small></button>`).join('')
            + '<div class="text-muted mt-1" style="font-size:12px">Gợi ý chỉ mang tính tham khảo, không thay thế chẩn đoán của bác sĩ.</div>';
          box.querySelectorAll('.suggest-pick').forEach((b) => b.addEventListener('click', () => {
            selectSpecialty(b.dataset.id, b.dataset.name);
            const symptoms = document.querySelector('textarea[name="symptoms"]');
            if (symptoms && !symptoms.value) symptoms.value = q;
          }));
        } catch (e) {
          box.innerHTML = `<span class="text-danger">${App.escape(e.message)}</span>`;
        }
      }
      if ($('symptomBtn')) {
        $('symptomBtn').addEventListener('click', suggest);
        $('symptomQuery').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); suggest(); } });
      }

      // ---------------- Bước 2: bác sĩ ----------------
      function renderDoctors() {
        const list = cfg.doctors.filter((d) => d.specialty_id === state.specialtyId);
        let html = `<div class="col-md-6"><div class="doctor-option d-flex align-items-center gap-3" data-id="any" role="button">
            <div class="icon-circle"><i class="bi bi-shuffle"></i></div>
            <div><div class="fw-semibold">Bất kỳ bác sĩ nào</div><small class="text-muted">Xem giờ trống của tất cả bác sĩ trong chuyên khoa</small></div></div></div>`;
        list.forEach((d) => {
          html += `<div class="col-md-6"><div class="doctor-option d-flex align-items-center gap-3" data-id="${d.id}" role="button">
            <img src="${App.escape(d.avatar)}" alt="">
            <div class="flex-grow-1 small">
              <div class="fw-semibold" style="font-size:15px">${App.escape(d.name)}</div>
              <div class="text-muted">${App.escape(d.position || '')}${d.experience ? ' · ' + d.experience + ' năm kinh nghiệm' : ''}</div>
              <div><i class="bi bi-star-fill text-warning"></i> ${Number(d.rating || 0).toFixed(1)} <span class="text-muted">(${d.rating_count})</span>
                · <span class="text-primary fw-semibold">${App.money(d.fee)}</span></div>
            </div></div></div>`;
        });
        if (!list.length) html += '<div class="col-12 text-muted small">Chuyên khoa chưa có bác sĩ nhận lịch.</div>';
        $('doctorList').innerHTML = html;
        $('doctorList').querySelectorAll('.doctor-option').forEach((el) => el.addEventListener('click', () => selectDoctor(el.dataset.id)));
      }

      function selectDoctor(id, userAction = true) {
        state.doctorId = id === 'any' ? 'any' : Number(id);
        const d = cfg.doctors.find((x) => x.id === state.doctorId);
        state.doctorName = d ? d.name : 'Bất kỳ bác sĩ nào';
        state.fee = d ? d.fee : null;
        clearSlot();
        $('doctorList').querySelectorAll('.doctor-option').forEach((el) => el.classList.toggle('active', String(el.dataset.id) === String(id)));
        $('step-time').classList.remove('d-none');
        emit();
        loadDays();
        if (userAction) scrollTo('step-time');
      }

      // ---------------- Bước 3: ngày & giờ ----------------
      function scope() {
        return state.doctorId === 'any' ? { specialty_id: state.specialtyId } : { doctor_id: state.doctorId };
      }

      function slotsUrl(params) {
        return cfg.slotsUrl + '?' + new URLSearchParams(Object.assign({}, scope(), params));
      }

      async function loadDays() {
        $('dateStrip').innerHTML = spinner;
        $('slotArea').innerHTML = '';
        try {
          const data = await App.fetchJson(slotsUrl({ from: cfg.today, days: cfg.days }));
          let first = null;
          $('dateStrip').innerHTML = data.days.map((d) => {
            if (!first && d.available > 0) first = d;
            return `<div class="date-chip ${d.available ? '' : 'off'}" data-date="${d.date}" data-label="${d.dow}, ${d.day}" data-available="${d.available}" role="button">
              <div class="dow">${d.dow}</div><div class="day">${d.day}</div>
              <div style="font-size:11px" class="${d.available ? 'text-success' : 'text-muted'}">${d.available ? d.available + ' giờ trống' : 'Hết chỗ'}</div></div>`;
          }).join('');
          $('dateStrip').querySelectorAll('.date-chip').forEach((el) => el.addEventListener('click', () => {
            if (Number(el.dataset.available) > 0) selectDate(el.dataset.date, el.dataset.label);
          }));
          if (first) {
            selectDate(first.date, `${first.dow}, ${first.day}`);
          } else {
            $('slotArea').innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-calendar-x fs-2 d-block mb-2"></i>Không còn khung giờ trống trong thời gian cho phép đặt. Vui lòng chọn bác sĩ khác hoặc gọi hotline.</div>';
          }
        } catch (e) {
          $('dateStrip').innerHTML = `<div class="text-danger small">${App.escape(e.message)}</div>`;
        }
      }

      function slotButtons(slots, doctor) {
        return slots.map((s) => {
          const disabled = !s.bookable || s.id === cfg.excludeSlotId;
          const note = s.id === cfg.excludeSlotId ? 'Lịch hiện tại' : (s.bookable ? `Còn ${s.available} chỗ` : (s.available > 0 ? 'Quá hạn đặt' : 'Hết chỗ'));
          return `<button type="button" class="slot-btn" ${disabled ? 'disabled' : ''} data-id="${s.id}" data-time="${s.time} - ${s.end}"
            data-doctor-name="${doctor ? App.escape(doctor.name) : ''}" data-fee="${doctor ? doctor.fee : ''}">
            <div class="time">${s.time}</div><div class="left">${note}</div></button>`;
        }).join('');
      }

      function byPeriod(slots, doctor) {
        const groups = {};
        slots.forEach((s) => { (groups[s.period] = groups[s.period] || []).push(s); });
        return Object.keys(groups).map((p) => `<div class="text-muted small text-uppercase fw-semibold mt-2 mb-2"><i class="bi ${p === 'Buổi sáng' ? 'bi-sun' : 'bi-sunset'} me-1"></i>${p}</div>
          <div class="slot-grid">${slotButtons(groups[p], doctor)}</div>`).join('');
      }

      async function selectDate(date, label) {
        state.date = date;
        state.dateLabel = label;
        clearSlot();
        if (state.doctorId === 'any') { state.doctorName = 'Bất kỳ bác sĩ nào'; state.fee = null; }
        emit();
        $('dateStrip').querySelectorAll('.date-chip').forEach((el) => el.classList.toggle('active', el.dataset.date === date));
        $('slotArea').innerHTML = spinner;
        try {
          const data = await App.fetchJson(slotsUrl({ date }));
          let html = '';
          if (state.doctorId === 'any') {
            html = (data.doctors || []).map((d) => `<div class="mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2 mb-1"><img src="${App.escape(d.avatar)}" class="rounded-circle" width="34" height="34" style="object-fit:cover" alt="">
                <strong>${App.escape(d.name)}</strong><span class="text-primary small ms-auto">${App.money(d.fee)}</span></div>
                ${byPeriod(d.slots, d)}</div>`).join('');
          } else {
            const d = cfg.doctors.find((x) => x.id === state.doctorId);
            html = byPeriod(data.slots || [], d);
          }
          $('slotArea').innerHTML = html || '<div class="text-muted text-center py-3">Không có khung giờ trong ngày này.</div>';
          $('slotArea').querySelectorAll('.slot-btn:not([disabled])').forEach((b) => b.addEventListener('click', () => selectSlot(b)));
        } catch (e) {
          $('slotArea').innerHTML = `<div class="text-danger small">${App.escape(e.message)}</div>`;
        }
      }

      function selectSlot(btn) {
        state.slotId = Number(btn.dataset.id);
        state.time = btn.dataset.time;
        if (state.doctorId === 'any') {
          state.doctorName = btn.dataset.doctorName + ' (tự phân)';
          state.fee = Number(btn.dataset.fee);
        }
        $('timeSlotInput').value = state.slotId;
        $('slotArea').querySelectorAll('.slot-btn').forEach((b) => b.classList.toggle('active', b === btn));
        emit();
      }

      // ---------------- Khởi tạo ----------------
      if (cfg.fixedSpecialty) {
        state.specialtyId = Number(cfg.fixedSpecialty.id);
        state.specialtyName = cfg.fixedSpecialty.name;
        renderDoctors();
        emit();
        if (cfg.prefill && cfg.prefill.doctor_id) selectDoctor(String(cfg.prefill.doctor_id), false);
      } else if (cfg.prefill && cfg.prefill.specialty_id) {
        const el = document.querySelector(`.specialty-option[data-id="${cfg.prefill.specialty_id}"]`);
        if (el) {
          selectSpecialty(el.dataset.id, el.dataset.name, false);
          if (cfg.prefill.doctor_id && cfg.doctors.some((d) => d.id === Number(cfg.prefill.doctor_id))) {
            selectDoctor(String(cfg.prefill.doctor_id), false);
          }
        }
      } else {
        emit();
      }
    })();
  </script>
@endpush
