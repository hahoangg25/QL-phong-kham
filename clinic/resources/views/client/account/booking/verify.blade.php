@extends('client.layouts.account')

@php $isReschedule = (bool) $hold->reschedule_from_id; @endphp

@section('title', $isReschedule ? 'Xác nhận đổi lịch' : 'Xác nhận đặt lịch')
@section('page_title', $isReschedule ? 'Xác nhận đổi lịch' : 'Xác nhận đặt lịch')
@section('page_subtitle', 'Nhập mã OTP trước khi hết thời gian giữ chỗ')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
  <li><a href="{{ route('account.booking.create') }}">Đặt lịch khám</a></li>
@endsection

@section('account_content')
  @php
    $slot = $hold->timeSlot;
    $doctor = $slot->doctor;
    $room = $slot->room ?? $doctor->room;
  @endphp

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card card-soft h-100">
        <div class="card-header">
          <i class="bi bi-calendar-check me-1"></i>{{ $isReschedule ? 'Lịch hẹn mới' : 'Thông tin lịch hẹn' }}
        </div>
        <div class="card-body">
          @if ($isReschedule && $hold->rescheduleFrom)
            <div class="alert alert-info small py-2">
              <i class="bi bi-arrow-repeat me-1"></i>Đổi từ lịch <strong>{{ $hold->rescheduleFrom->code }}</strong>
              ({{ $hold->rescheduleFrom->scheduled_at->format('H:i d/m/Y') }}). Sau khi xác nhận, lịch cũ chuyển sang "Đã đổi lịch".
            </div>
          @endif
          <div class="d-flex align-items-center gap-3 mb-3">
            <img src="{{ $doctor->avatarUrl() }}" class="rounded-circle" width="64" height="64" style="object-fit:cover" alt="">
            <div>
              <div class="fw-semibold fs-5">{{ $doctor->display_name }}</div>
              <div class="text-muted small">{{ $hold->specialty->name }} · Phí khám <span class="text-primary fw-semibold">{{ money($doctor->consultation_fee) }}</span></div>
            </div>
          </div>
          <dl class="row mb-0">
            <dt class="col-sm-4 text-muted fw-normal">Người khám</dt>
            <dd class="col-sm-8 fw-semibold">{{ $hold->patient->full_name }} <span class="text-muted fw-normal small">({{ $hold->patient->relationship }})</span></dd>
            <dt class="col-sm-4 text-muted fw-normal">Thời gian</dt>
            <dd class="col-sm-8 fw-semibold">{{ $slot->timeLabel() }}, {{ $slot->date->translatedFormat('l, d/m/Y') }}</dd>
            <dt class="col-sm-4 text-muted fw-normal">Phòng khám</dt>
            <dd class="col-sm-8">{{ $room?->label() ?? 'Sẽ thông báo khi check-in' }}</dd>
            @if ($hold->symptoms)
              <dt class="col-sm-4 text-muted fw-normal">Triệu chứng</dt>
              <dd class="col-sm-8">{{ $hold->symptoms }}</dd>
            @endif
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card card-soft text-center h-100">
        <div class="card-body p-4">
          <div class="icon-circle mx-auto mb-2"><i class="bi bi-shield-lock"></i></div>
          <h5 class="mb-1">Nhập mã OTP</h5>
          <p class="small text-muted mb-2">Mã gồm 6 chữ số đã gửi tới <strong>{{ auth()->user()->email }}</strong></p>
          <div class="mb-3">
            Thời gian giữ chỗ còn lại:
            <span class="countdown fs-4 text-danger" id="countdown" data-expires="{{ $hold->expires_at->toIso8601String() }}">--:--</span>
          </div>
          <form method="POST" action="{{ route('account.booking.confirm', $hold) }}" id="otpForm">
            @csrf
            <input type="text" name="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
              class="form-control otp-input mx-auto mb-3 @error('otp') is-invalid @enderror" placeholder="______" required autofocus>
            <button class="btn btn-primary w-100 py-2" id="otpSubmit"><i class="bi bi-check2-circle me-1"></i>{{ $isReschedule ? 'Xác nhận đổi lịch' : 'Xác nhận đặt lịch' }}</button>
          </form>
          <div class="d-flex justify-content-between mt-3">
            <form method="POST" action="{{ route('account.booking.resend', $hold) }}">
              @csrf
              <button class="btn btn-link btn-sm px-0">Gửi lại mã</button>
            </form>
            <form method="POST" action="{{ route('account.booking.release', $hold) }}" data-confirm="Hủy giữ chỗ và chọn khung giờ khác?">
              @csrf
              @method('DELETE')
              <button class="btn btn-link btn-sm px-0 text-danger">Chọn giờ khác</button>
            </form>
          </div>
          <div id="expiredBox" class="alert alert-warning small mt-3 d-none">
            Đã hết thời gian giữ chỗ. <a href="{{ $isReschedule ? route('account.appointments.reschedule', $hold->reschedule_from_id) : route('account.booking.create') }}">Chọn lại khung giờ</a>.
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      const el = document.getElementById('countdown');
      const expires = new Date(el.dataset.expires).getTime();
      function tick() {
        const left = Math.max(0, Math.floor((expires - Date.now()) / 1000));
        el.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
        if (left <= 0) {
          document.getElementById('otpSubmit').disabled = true;
          document.getElementById('expiredBox').classList.remove('d-none');
          clearInterval(timer);
        }
      }
      const timer = setInterval(tick, 1000);
      tick();
    })();
  </script>
@endpush
