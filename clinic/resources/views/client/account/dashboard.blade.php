@extends('client.layouts.account')

@section('title', 'Tài khoản của tôi')
@section('page_title', 'Tài khoản của tôi')

@section('account_content')
  <div class="card card-soft mb-3 overflow-hidden">
    <div class="card-body d-flex flex-wrap align-items-center gap-3" style="background: linear-gradient(135deg, #eaf3fc, #ffffff)">
      <div class="flex-grow-1">
        <h2 class="h4 mb-1">Xin chào, {{ $user->name }}!</h2>
        <p class="text-muted mb-0">Chủ động đặt lịch khám, theo dõi lịch hẹn và kết quả khám của bạn và người thân.</p>
      </div>
      <a href="{{ route('account.booking.create') }}" class="btn btn-primary"><i class="bi bi-calendar-plus me-1"></i>Đặt lịch khám</a>
    </div>
  </div>

  @if ($user->no_show_count > 0)
    @php $exceeded = $user->no_show_count >= $noShowThreshold; @endphp
    <div class="alert {{ $exceeded ? 'alert-danger' : 'alert-warning' }} d-flex gap-2">
      <i class="bi bi-exclamation-triangle fs-5"></i>
      <div>
        Tài khoản đã vắng mặt <strong>{{ $user->no_show_count }}/{{ $noShowThreshold }}</strong> lần (bao gồm hủy lịch sát giờ - BR-04).
        @if ($exceeded)
          {{ $noShowPolicy === 'block'
              ? 'Tài khoản đang bị hạn chế đặt lịch online, vui lòng liên hệ hotline '.config('clinic.hotline').'.'
              : 'Các lịch hẹn mới cần đặt cọc '.money(setting('deposit_amount')).' (BR-05).' }}
        @else
          Khi đạt {{ $noShowThreshold }} lần, tài khoản sẽ {{ $noShowPolicy === 'block' ? 'bị hạn chế đặt lịch online' : 'phải đặt cọc khi đặt lịch' }}.
        @endif
      </div>
    </div>
  @endif

  <div class="row g-3 mb-3">
    @foreach ([
        ['bi-people', 'Hồ sơ bệnh nhân', $stats['profiles'], route('account.patients.index')],
        ['bi-calendar2-week', 'Lịch hẹn sắp tới', $stats['upcoming'], route('account.appointments.index')],
        ['bi-journal-medical', 'Lượt khám đã hoàn thành', $stats['visits'], route('account.visits.index')],
    ] as [$icon, $label, $value, $url])
      <div class="col-md-4">
        <a href="{{ $url }}" class="card card-soft text-decoration-none h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="icon-circle"><i class="bi {{ $icon }}"></i></div>
            <div>
              <div class="fs-3 fw-bold text-dark lh-1">{{ $value }}</div>
              <div class="small text-muted">{{ $label }}</div>
            </div>
          </div>
        </a>
      </div>
    @endforeach
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card card-soft h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-calendar-event me-1"></i>Lịch hẹn sắp tới</span>
          <a href="{{ route('account.appointments.index') }}" class="small">Xem tất cả</a>
        </div>
        <div class="list-group list-group-flush">
          @forelse ($upcoming as $a)
            <a href="{{ route('account.appointments.show', $a) }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center py-3">
              <div class="text-center rounded-3 px-2 py-1" style="background:#eaf3fc;min-width:64px">
                <div class="fw-bold text-primary lh-1">{{ $a->scheduled_at->format('d/m') }}</div>
                <div class="small fw-semibold">{{ $a->scheduled_at->format('H:i') }}</div>
              </div>
              <div class="flex-grow-1 small">
                <div class="fw-semibold" style="font-size:15px">{{ $a->specialty->name }}</div>
                <div class="text-muted">{{ $a->doctor->display_name }} · {{ $a->patient->full_name }}</div>
              </div>
              {!! status_badge($a->status) !!}
            </a>
          @empty
            <div class="text-center text-muted py-4">
              <i class="bi bi-calendar2-plus fs-2 d-block mb-2"></i>Bạn chưa có lịch hẹn sắp tới.
              <div class="mt-2"><a href="{{ route('account.booking.create') }}" class="btn btn-sm btn-outline-primary">Đặt lịch ngay</a></div>
            </div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card card-soft h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-bell me-1"></i>Thông báo mới</span>
          <a href="{{ route('account.notifications.index') }}" class="small">Tất cả</a>
        </div>
        <div class="list-group list-group-flush">
          @forelse ($notifications as $n)
            <form method="POST" action="{{ route('account.notifications.read', $n->id) }}" class="list-group-item list-group-item-action p-0">
              @csrf
              <button class="btn w-100 text-start border-0 bg-transparent px-3 py-2 small">
                <div class="fw-semibold"><i class="bi {{ $n->data['icon'] ?? 'bi-bell' }} me-1 text-primary"></i>{{ $n->data['title'] ?? 'Thông báo' }}</div>
                <div class="text-muted">{{ \Illuminate\Support\Str::limit($n->data['message'] ?? '', 90) }}</div>
                <div class="text-muted" style="font-size:11px">{{ $n->created_at->diffForHumans() }}</div>
              </button>
            </form>
          @empty
            <div class="text-center text-muted py-4 small"><i class="bi bi-bell-slash fs-3 d-block mb-1"></i>Không có thông báo mới.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
@endsection
