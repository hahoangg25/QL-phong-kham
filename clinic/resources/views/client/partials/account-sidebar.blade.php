{{-- ================================================================= --}}
{{-- BLOCK CỐ ĐỊNH: SIDEBAR TRANG CÁ NHÂN CỦA BỆNH NHÂN (CLIENT ACCOUNT)--}}
{{-- ================================================================= --}}
<aside class="col-lg-3">
  <div class="account-sidebar card border-0 shadow-sm">
    <div class="card-body text-center border-bottom">
      <img src="{{ auth()->user()->avatarUrl() }}" class="rounded-circle mb-2" width="72" height="72" alt="">
      <h6 class="mb-0">{{ auth()->user()->name }}</h6>
      <small class="text-muted">{{ auth()->user()->email ?? auth()->user()->phone }}</small>
    </div>
    <nav class="list-group list-group-flush">
      @php
        $menu = [
            ['account.dashboard', 'bi-grid', 'Tổng quan', 'account.dashboard'],
            ['account.booking.create', 'bi-calendar-plus', 'Đặt lịch khám', 'account.booking.*'],
            ['account.appointments.index', 'bi-calendar2-week', 'Lịch hẹn của tôi', 'account.appointments.*'],
            ['account.visits.index', 'bi-journal-medical', 'Lịch sử khám', 'account.visits.*'],
            ['account.invoices.index', 'bi-receipt', 'Hóa đơn', 'account.invoices.*'],
            ['account.patients.index', 'bi-people', 'Hồ sơ người thân', 'account.patients.*'],
            ['account.notifications.index', 'bi-bell', 'Thông báo', 'account.notifications.*'],
            ['account.profile.edit', 'bi-person-gear', 'Thông tin tài khoản', 'account.profile.*'],
        ];
      @endphp
      @foreach ($menu as [$route, $icon, $label, $pattern])
        <a href="{{ route($route) }}" class="list-group-item list-group-item-action d-flex align-items-center {{ request()->routeIs($pattern) ? 'active' : '' }}">
          <i class="bi {{ $icon }} me-2"></i>{{ $label }}
          @if ($route === 'account.notifications.index' && !empty($unreadNotifications))
            <span class="badge bg-danger rounded-pill ms-auto">{{ $unreadNotifications }}</span>
          @endif
        </a>
      @endforeach
      <form method="POST" action="{{ route('logout') }}" class="list-group-item p-0">@csrf
        <button class="btn btn-link text-danger text-decoration-none w-100 text-start px-3 py-2"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</button>
      </form>
    </nav>
  </div>
</aside>
