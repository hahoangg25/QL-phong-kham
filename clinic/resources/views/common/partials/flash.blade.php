{{-- Thông báo flash dùng chung cho cổng bệnh nhân và hệ thống nội bộ --}}
@if (session('dev_otp'))
  <div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2 mt-3" role="alert">
    <i class="bi bi-shield-lock fs-5"></i>
    <div><strong>Chế độ demo:</strong> mã OTP vừa gửi là <code class="fs-5 fw-bold">{{ session('dev_otp') }}</code>
      <span class="text-muted small">(email đang chạy driver "log" nên mã được hiển thị tại đây; tắt bằng CLINIC_SHOW_OTP=false)</span></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
  </div>
@endif

@foreach (['success' => 'check-circle', 'error' => 'exclamation-octagon', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'] as $type => $icon)
  @if (session($type))
    <div class="alert alert-{{ $type === 'error' ? 'danger' : $type }} alert-dismissible fade show d-flex align-items-start gap-2 mt-3" role="alert">
      <i class="bi bi-{{ $icon }} fs-5"></i>
      <div>
        @if (is_array(session($type)))
          <ul class="mb-0 ps-3">@foreach (session($type) as $msg)<li>{{ $msg }}</li>@endforeach</ul>
        @else
          {{ session($type) }}
        @endif
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
  @endif
@endforeach

@if ($errors->any())
  <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
    <div class="d-flex gap-2"><i class="bi bi-exclamation-octagon fs-5"></i>
      <div>
        <strong>Vui lòng kiểm tra lại thông tin:</strong>
        <ul class="mb-0 ps-3">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
  </div>
@endif
