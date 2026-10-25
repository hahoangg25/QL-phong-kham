@extends('client.layouts.account')

@section('title', 'Đặt lịch khám')
@section('page_title', 'Đặt lịch khám')
@section('page_subtitle', 'Chọn chuyên khoa, bác sĩ và khung giờ phù hợp - xác nhận bằng mã OTP')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
@endsection

@section('account_content')
  <div class="step-indicator">
    <div class="step" data-step="1"><i class="bi bi-hospital me-1"></i>1. Chuyên khoa</div>
    <div class="step" data-step="2"><i class="bi bi-person-badge me-1"></i>2. Bác sĩ</div>
    <div class="step" data-step="3"><i class="bi bi-clock me-1"></i>3. Ngày & giờ</div>
    <div class="step" data-step="4"><i class="bi bi-person-check me-1"></i>4. Người khám</div>
  </div>

  @unless ($canBook)
    <div class="alert alert-danger d-flex gap-2">
      <i class="bi bi-slash-circle fs-5"></i>
      <div>
        <strong>Tài khoản đang bị hạn chế đặt lịch online.</strong>
        Bạn đã vắng mặt {{ $user->no_show_count }} lần (ngưỡng {{ setting('no_show_threshold') }} lần - BR-05).
        Vui lòng gọi hotline <strong>{{ config('clinic.hotline') }}</strong> để được lễ tân hỗ trợ đặt lịch.
      </div>
    </div>
  @endunless

  @if ($deposit > 0)
    <div class="alert alert-warning d-flex gap-2">
      <i class="bi bi-wallet2 fs-5"></i>
      <div>Do tài khoản đã vắng mặt {{ $user->no_show_count }} lần, lịch hẹn mới cần <strong>đặt cọc {{ money($deposit) }}</strong>
        (thanh toán online sau khi xác nhận OTP). Tiền cọc được trừ vào hóa đơn khi khám.</div>
    </div>
  @endif

  <form method="POST" action="{{ route('account.booking.store') }}" id="bookingForm">
    @csrf
    <div class="row g-3">
      <div class="col-xl-8">
        @include('client.account.booking._picker', [
            'mode' => 'booking',
            'specialties' => $specialties,
            'doctorsJson' => $doctorsJson,
            'prefill' => $prefill,
        ])

        <div class="card card-soft mb-3 d-none" id="step-patient">
          <div class="card-header"><span class="badge bg-primary rounded-pill me-2">4</span>Thông tin người đi khám</div>
          <div class="card-body">
            @php $selectedPatient = old('patient_id', $prefill['patient_id'] ?? $patients->first()?->id); @endphp
            <div class="row g-2 mb-2">
              @foreach ($patients as $p)
                <div class="col-md-6">
                  <label class="doctor-option d-flex align-items-center gap-2 w-100 mb-0 patient-option">
                    <input type="radio" class="form-check-input mt-0" name="patient_id" value="{{ $p->id }}" @checked((int) $selectedPatient === $p->id)>
                    <span class="icon-circle" style="width:40px;height:40px;font-size:18px"><i class="bi bi-person"></i></span>
                    <span class="small">
                      <span class="fw-semibold d-block" style="font-size:15px">{{ $p->full_name }}</span>
                      <span class="text-muted">{{ $p->relationship }} · {{ $p->genderLabel() }}{{ $p->age !== null ? ' · '.$p->age.' tuổi' : '' }}</span>
                    </span>
                  </label>
                </div>
              @endforeach
              <div class="col-md-6">
                <a href="{{ route('account.patients.create', ['redirect_to' => 'booking']) }}" class="doctor-option d-flex align-items-center gap-2 text-decoration-none h-100">
                  <span class="icon-circle" style="width:40px;height:40px;font-size:18px"><i class="bi bi-plus-lg"></i></span>
                  <span class="small"><span class="fw-semibold d-block">Thêm hồ sơ người thân</span><span class="text-muted">Đặt lịch cho bố mẹ, con...</span></span>
                </a>
              </div>
            </div>

            <label class="form-label mt-3" for="symptoms">Triệu chứng / lý do khám</label>
            <textarea name="symptoms" id="symptoms" rows="3" maxlength="1000" class="form-control" placeholder="Mô tả ngắn triệu chứng, thời gian xuất hiện, thuốc đang dùng...">{{ old('symptoms') }}</textarea>
          </div>
        </div>
      </div>

      <div class="col-xl-4">
        <div class="card card-soft" style="position: sticky; top: 110px;">
          <div class="card-header"><i class="bi bi-clipboard-check me-1"></i>Thông tin lịch hẹn</div>
          <div class="card-body small">
            <dl class="row mb-0">
              <dt class="col-5 text-muted fw-normal">Chuyên khoa</dt><dd class="col-7 fw-semibold" data-sum="specialty">—</dd>
              <dt class="col-5 text-muted fw-normal">Bác sĩ</dt><dd class="col-7 fw-semibold" data-sum="doctor">—</dd>
              <dt class="col-5 text-muted fw-normal">Ngày khám</dt><dd class="col-7 fw-semibold" data-sum="date">—</dd>
              <dt class="col-5 text-muted fw-normal">Giờ khám</dt><dd class="col-7 fw-semibold" data-sum="time">—</dd>
              <dt class="col-5 text-muted fw-normal">Phí khám</dt><dd class="col-7 fw-semibold text-primary" data-sum="fee">—</dd>
            </dl>
            <hr>
            <ul class="text-muted ps-3 mb-3" style="font-size:12.5px">
              <li>Đặt trước giờ khám ít nhất <strong>{{ $rules['min_hours'] }} giờ</strong>, tối đa <strong>{{ $rules['max_days'] }} ngày</strong>.</li>
              <li>Khung giờ được giữ <strong>{{ $rules['hold_minutes'] }} phút</strong> trong lúc bạn nhập mã OTP gửi qua email.</li>
              <li>Mỗi hồ sơ chỉ đặt 1 lịch/chuyên khoa/ngày.</li>
              <li>Hủy miễn phí trước giờ khám {{ setting('cancel_free_hours_before') }} giờ.</li>
            </ul>
            <button type="submit" class="btn btn-primary w-100 py-2" id="bookingSubmit" disabled>
              <i class="bi bi-shield-lock me-1"></i>Giữ chỗ & nhận mã OTP
            </button>
            <div class="text-center text-muted mt-2" style="font-size:12px" id="bookingHint">Vui lòng chọn khung giờ khám.</div>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection

@push('scripts')
  <script>
    (function () {
      const canBook = @json($canBook);
      const submit = document.getElementById('bookingSubmit');
      const hint = document.getElementById('bookingHint');
      const stepPatient = document.getElementById('step-patient');

      function refresh() {
        const slot = document.getElementById('timeSlotInput').value;
        const patient = document.querySelector('input[name="patient_id"]:checked');
        stepPatient.classList.toggle('d-none', !slot);
        document.querySelectorAll('.patient-option').forEach((el) => el.classList.toggle('active', el.querySelector('input').checked));
        const step4 = document.querySelector('.step-indicator .step[data-step="4"]');
        if (slot) {
          step4.classList.toggle('done', !!patient);
          step4.classList.toggle('active', !patient);
        } else {
          step4.classList.remove('done', 'active');
        }
        const ready = canBook && slot && patient;
        submit.disabled = !ready;
        hint.textContent = !canBook ? 'Tài khoản đang bị hạn chế đặt online.'
          : (!slot ? 'Vui lòng chọn khung giờ khám.' : (!patient ? 'Vui lòng chọn hồ sơ người đi khám.' : 'Sẵn sàng giữ chỗ.'));
      }

      document.addEventListener('booking:change', (e) => {
        const wasHidden = stepPatient.classList.contains('d-none');
        refresh();
        if (e.detail.slotId && wasHidden) {
          window.scrollTo({ top: stepPatient.getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth' });
        }
      });
      document.querySelectorAll('input[name="patient_id"]').forEach((el) => el.addEventListener('change', refresh));
      refresh();
    })();
  </script>
@endpush
