{{--
  Chọn bệnh nhân có sẵn (tìm theo tên/SĐT/mã/CCCD) hoặc nhập nhanh bệnh nhân mới.
  Biến: $patient (Patient|null - chọn sẵn), $genders, $extended (bool - thêm CCCD, địa chỉ cho tiếp nhận vãng lai).
  Phát sự kiện document "patient:selected" (detail = dữ liệu bệnh nhân hoặc null) khi chọn/bỏ chọn.
--}}
@php
  $extended = $extended ?? false;
  $selected = $patient ?? (old('patient_id') ? \App\Models\Clinical\Patient::find(old('patient_id')) : null);
  $mode = old('patient_mode', $selected || ! old('full_name') ? 'existing' : 'new');
  $selectedJson = $selected ? [
      'id' => $selected->id, 'code' => $selected->code, 'full_name' => $selected->full_name, 'phone' => $selected->phone,
      'date_of_birth' => $selected->date_of_birth?->format('d/m/Y'), 'age' => $selected->age, 'gender' => $selected->genderLabel(),
      'priority' => $selected->isPriority(), 'summary' => $selected->summary(),
  ] : null;
@endphp

<div class="patient-picker" id="patientPicker">
  <div class="btn-group btn-group-sm mb-3" role="group">
    <input type="radio" class="btn-check" name="patient_mode" id="pmExisting" value="existing" @checked($mode === 'existing')>
    <label class="btn btn-outline-primary" for="pmExisting"><i class="bi bi-search me-1"></i>Bệnh nhân có sẵn</label>
    <input type="radio" class="btn-check" name="patient_mode" id="pmNew" value="new" @checked($mode === 'new')>
    <label class="btn btn-outline-primary" for="pmNew"><i class="bi bi-person-plus me-1"></i>Bệnh nhân mới</label>
  </div>

  <div id="pmExistingBox">
    <input type="hidden" name="patient_id" id="patientId" value="{{ $selected?->id }}">
    <div class="ac-wrap" id="patientSearchWrap" @if ($selected) style="display:none" @endif>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" class="form-control" id="patientSearch" placeholder="Nhập tên, số điện thoại, mã BN hoặc CCCD (tối thiểu 2 ký tự)" autocomplete="off">
      </div>
      <div class="ac-list" id="patientResults" style="display:none"></div>
    </div>
    <div id="patientSelected" class="border rounded p-3 bg-light" @unless ($selected) style="display:none" @endunless>
      <div class="d-flex align-items-start gap-3">
        <div class="stat-icon soft-primary rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px"><i class="bi bi-person"></i></div>
        <div class="flex-grow-1">
          <div class="fw-semibold" id="psName">{{ $selected?->full_name }}</div>
          <div class="small text-muted" id="psInfo">{{ $selected ? $selected->code.' · '.$selected->summary().' · '.$selected->phone : '' }}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="psChange">Đổi</button>
      </div>
    </div>
  </div>

  <fieldset id="pmNewBox" @disabled($mode !== 'new') @if ($mode !== 'new') style="display:none" @endif>
    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label required">Họ tên</label>
        <input type="text" name="full_name" value="{{ old('full_name') }}" class="form-control @error('full_name') is-invalid @enderror">
      </div>
      <div class="col-md-6">
        <label class="form-label {{ $extended ? '' : 'required' }}">Số điện thoại</label>
        <input type="text" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror" placeholder="09xxxxxxxx">
      </div>
      <div class="col-md-6">
        <label class="form-label">Ngày sinh</label>
        <input type="date" name="date_of_birth" id="newDob" value="{{ old('date_of_birth') }}" max="{{ today()->toDateString() }}" class="form-control @error('date_of_birth') is-invalid @enderror">
      </div>
      <div class="col-md-6">
        <label class="form-label">Giới tính</label>
        <select name="gender" class="form-select">
          <option value="">-- Chọn --</option>
          @foreach ($genders as $value => $label)
            <option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      @if ($extended)
        <div class="col-md-6">
          <label class="form-label">Số CCCD</label>
          <input type="text" name="id_number" value="{{ old('id_number') }}" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Địa chỉ</label>
          <input type="text" name="address" value="{{ old('address') }}" class="form-control">
        </div>
      @endif
    </div>
  </fieldset>
</div>

@push('scripts')
  <script>
    (function () {
      const searchUrl = @json(route('staff.patients.search'));
      const box = {
        existing: document.getElementById('pmExistingBox'),
        newBox: document.getElementById('pmNewBox'),
        id: document.getElementById('patientId'),
        wrap: document.getElementById('patientSearchWrap'),
        input: document.getElementById('patientSearch'),
        list: document.getElementById('patientResults'),
        selected: document.getElementById('patientSelected'),
      };
      const emit = (detail) => document.dispatchEvent(new CustomEvent('patient:selected', { detail }));

      function setMode(mode) {
        const isNew = mode === 'new';
        box.existing.style.display = isNew ? 'none' : '';
        box.newBox.style.display = isNew ? '' : 'none';
        box.newBox.disabled = !isNew;
        box.id.disabled = isNew;
        emit(isNew ? null : (window.__selectedPatient || null));
      }
      document.querySelectorAll('[name="patient_mode"]').forEach((r) => r.addEventListener('change', () => setMode(r.value)));

      function select(p) {
        window.__selectedPatient = p;
        box.id.value = p ? p.id : '';
        box.wrap.style.display = p ? 'none' : '';
        box.selected.style.display = p ? '' : 'none';
        if (p) {
          document.getElementById('psName').textContent = p.full_name;
          document.getElementById('psInfo').textContent = [p.code, p.summary, p.phone].filter(Boolean).join(' · ');
        } else {
          box.input.value = '';
          box.input.focus();
        }
        emit(p);
      }
      document.getElementById('psChange').addEventListener('click', () => select(null));

      let timer;
      box.input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = box.input.value.trim();
        if (q.length < 2) { box.list.style.display = 'none'; return; }
        timer = setTimeout(async () => {
          try {
            const rows = await App.fetchJson(searchUrl + '?q=' + encodeURIComponent(q));
            box.list.innerHTML = rows.length
              ? rows.map((p, i) => `<div class="ac-item" data-i="${i}"><strong>${App.escape(p.full_name)}</strong>
                  ${p.priority ? '<span class="badge bg-warning text-dark ms-1">Ưu tiên</span>' : ''}
                  <br><small>${App.escape(p.code)} · ${App.escape(p.gender || '')} ${p.date_of_birth ? '· ' + App.escape(p.date_of_birth) : ''} · ${App.escape(p.phone || '')}</small></div>`).join('')
              : '<div class="ac-item text-muted">Không tìm thấy. Chọn "Bệnh nhân mới" để thêm.</div>';
            box.list.style.display = '';
            box.list.querySelectorAll('[data-i]').forEach((el) => el.addEventListener('click', () => {
              select(rows[el.dataset.i]);
              box.list.style.display = 'none';
            }));
          } catch (e) { box.list.style.display = 'none'; }
        }, 250);
      });
      document.addEventListener('click', (e) => { if (!box.wrap.contains(e.target)) box.list.style.display = 'none'; });

      window.__selectedPatient = @json($selectedJson);
      setMode(document.querySelector('[name="patient_mode"]:checked').value);
    })();
  </script>
@endpush
