@extends('admin.layouts.app')

@section('title', 'Tiếp nhận vãng lai')
@section('page_title', 'Tiếp nhận bệnh nhân vãng lai')
@section('breadcrumbs', 'Lễ tân / Tiếp nhận vãng lai')

@section('content')
  @include('admin.reception._ticket_alert')

  <form method="POST" action="{{ route('staff.reception.walkin.store') }}">
    @csrf
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><span class="badge bg-primary me-2">1</span>Thông tin bệnh nhân</div>
          <div class="card-body">
            @include('admin.reception.patients._picker', ['patient' => $patient, 'genders' => $genders, 'extended' => true])
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><span class="badge bg-primary me-2">2</span>Phân bác sĩ và lý do khám</div>
          <div class="card-body">
            <label class="form-label required">Bác sĩ khám</label>
            <select name="doctor_id" class="form-select mb-1 @error('doctor_id') is-invalid @enderror" required>
              <option value="">-- Chọn bác sĩ --</option>
              @foreach ($doctors->groupBy(fn ($d) => $d['working'] ? 'Có lịch làm việc hôm nay' : 'Không có ca hôm nay') as $group => $items)
                <optgroup label="{{ $group }}">
                  @foreach ($items as $d)
                    <option value="{{ $d['id'] }}" @selected(old('doctor_id', request('doctor_id')) == $d['id'])>
                      {{ $d['specialty'] }} - {{ $d['name'] }}{{ $d['room'] ? ' (P.'.$d['room'].')' : '' }} · đang chờ {{ $d['waiting'] }}
                    </option>
                  @endforeach
                </optgroup>
              @endforeach
            </select>
            <div class="form-text mb-3">Số trong ngoặc là số bệnh nhân đang chờ của bác sĩ - nên ưu tiên bác sĩ ít người chờ.</div>

            <label class="form-label">Lý do khám / triệu chứng</label>
            <textarea name="reason" rows="3" class="form-control mb-3" placeholder="vd: Đau họng, sốt nhẹ từ hôm qua">{{ old('reason') }}</textarea>

            <div class="form-check form-switch p-3 rounded bg-light ps-5">
              <input class="form-check-input" type="checkbox" name="priority" value="1" id="priority" @checked(old('priority'))>
              <label class="form-check-label" for="priority">
                Ưu tiên trong hàng đợi
                <span class="d-block small text-muted">Tự đánh dấu cho người từ {{ $priorityAges[0] }} tuổi trở lên hoặc trẻ dưới {{ $priorityAges[1] }} tuổi (LT-08).</span>
              </label>
            </div>
            <div class="small text-warning mt-2" id="priorityHint" style="display:none"><i class="bi bi-star-fill me-1"></i>Bệnh nhân thuộc diện ưu tiên theo độ tuổi.</div>
          </div>
          <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('staff.queue.index') }}" class="btn btn-outline-secondary">Xem hàng đợi</a>
            <button class="btn btn-primary"><i class="bi bi-ticket-perforated me-1"></i>Tiếp nhận & cấp số</button>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection

@push('scripts')
  <script>
    (function () {
      const [elderly, child] = @json($priorityAges);
      const box = document.getElementById('priority');
      const hint = document.getElementById('priorityHint');
      const apply = (isPriority) => {
        hint.style.display = isPriority ? '' : 'none';
        if (isPriority) box.checked = true;
      };
      document.addEventListener('patient:selected', (e) => apply(!!(e.detail && e.detail.priority)));
      const pid = document.getElementById('patientId');
      if (pid && !pid.disabled && window.__selectedPatient) apply(!!window.__selectedPatient.priority);
      const dob = document.getElementById('newDob');
      if (dob) dob.addEventListener('change', () => {
        if (!dob.value) return apply(false);
        const b = new Date(dob.value), n = new Date();
        let age = n.getFullYear() - b.getFullYear();
        if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) age--;
        apply(age >= elderly || age < child);
      });
    })();
  </script>
@endpush
