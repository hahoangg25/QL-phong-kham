@extends('client.layouts.account')

@section('title', 'Đổi lịch hẹn '.$appointment->code)
@section('page_title', 'Đổi lịch hẹn')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
  <li><a href="{{ route('account.appointments.show', $appointment) }}">Lịch hẹn {{ $appointment->code }}</a></li>
@endsection

@section('account_title', 'Đổi lịch hẹn '.$appointment->code)
@section('account_actions')
  <a href="{{ route('account.appointments.show', $appointment) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
@endsection

@section('account_content')
  <div class="alert alert-info d-flex gap-2 small">
    <i class="bi bi-info-circle fs-5"></i>
    <div>
      Lịch hiện tại: <strong>{{ $appointment->patient->full_name }}</strong> - {{ $appointment->specialty->name }},
      {{ $appointment->doctor->display_name }}, <strong>{{ $appointment->timeLabel() }}</strong>.<br>
      Chọn khung giờ mới cùng chuyên khoa (có thể đổi bác sĩ). Lịch cũ chỉ chuyển sang "Đã đổi lịch" sau khi bạn xác nhận OTP; khoản đã thanh toán trước được chuyển sang lịch mới.
    </div>
  </div>

  <form method="POST" action="{{ route('account.appointments.reschedule.store', $appointment) }}">
    @csrf
    <div class="row g-3">
      <div class="col-xl-8">
        @include('client.account.booking._picker', [
            'mode' => 'reschedule',
            'doctorsJson' => $doctorsJson,
            'prefill' => ['doctor_id' => $appointment->doctor_id],
            'fixedSpecialty' => ['id' => $appointment->specialty_id, 'name' => $appointment->specialty->name],
            'excludeSlotId' => $appointment->time_slot_id,
        ])
        <div class="card card-soft mb-3">
          <div class="card-body">
            <label class="form-label" for="symptoms">Triệu chứng / ghi chú (có thể cập nhật)</label>
            <textarea name="symptoms" id="symptoms" rows="2" maxlength="1000" class="form-control">{{ old('symptoms', $appointment->symptoms) }}</textarea>
          </div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="card card-soft" style="position: sticky; top: 110px;">
          <div class="card-header"><i class="bi bi-arrow-repeat me-1"></i>Lịch mới</div>
          <div class="card-body small">
            <dl class="row mb-0">
              <dt class="col-5 text-muted fw-normal">Bác sĩ</dt><dd class="col-7 fw-semibold" data-sum="doctor">—</dd>
              <dt class="col-5 text-muted fw-normal">Ngày khám</dt><dd class="col-7 fw-semibold" data-sum="date">—</dd>
              <dt class="col-5 text-muted fw-normal">Giờ khám</dt><dd class="col-7 fw-semibold" data-sum="time">—</dd>
              <dt class="col-5 text-muted fw-normal">Phí khám</dt><dd class="col-7 fw-semibold text-primary" data-sum="fee">—</dd>
            </dl>
            <hr>
            <p class="text-muted" style="font-size:12.5px">Khung giờ mới được giữ {{ $rules['hold_minutes'] }} phút trong lúc bạn nhập mã OTP.</p>
            <button class="btn btn-primary w-100" id="rescheduleSubmit" disabled><i class="bi bi-shield-lock me-1"></i>Giữ chỗ & nhận OTP</button>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection

@push('scripts')
  <script>
    document.addEventListener('booking:change', (e) => {
      document.getElementById('rescheduleSubmit').disabled = !e.detail.slotId;
    });
  </script>
@endpush
