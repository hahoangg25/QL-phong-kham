{{-- ================================================================= --}}
{{-- BLOCK CỐ ĐỊNH: HEADER & THANH ĐIỀU HƯỚNG CỔNG BỆNH NHÂN (CLIENT) --}}
{{-- ================================================================= --}}
<header id="header" class="header fixed-top">

  {{-- Topbar liên hệ & giờ làm việc --}}
  <div class="topbar d-flex align-items-center dark-background">
    <div class="container d-flex justify-content-center justify-content-md-between">
      <div class="contact-info d-flex align-items-center">
        <i class="bi bi-envelope d-flex align-items-center"><a href="mailto:{{ config('clinic.email') }}">{{ config('clinic.email') }}</a></i>
        <i class="bi bi-phone d-flex align-items-center ms-4"><span>Hotline: {{ config('clinic.hotline') }}</span></i>
      </div>
      <div class="d-none d-md-flex align-items-center small">
        <i class="bi bi-clock me-2"></i>{{ config('clinic.working_hours') }}
      </div>
    </div>
  </div>

  {{-- Branding Logo & Menu chính --}}
  <div class="branding d-flex align-items-center">
    <div class="container position-relative d-flex align-items-center justify-content-between">
      <a href="{{ route('home') }}" class="logo d-flex align-items-center">
        <span class="logo-mark"><i class="fa-solid fa-tooth"></i></span>
        <h1 class="sitename">Lộc Phát <small>Dental Clinic</small></h1>
      </a>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Trang chủ</a></li>
          <li><a href="{{ route('specialties.index') }}" class="{{ request()->routeIs('specialties.*') ? 'active' : '' }}">Chuyên khoa</a></li>
          <li><a href="{{ route('doctors.index') }}" class="{{ request()->routeIs('doctors.*') ? 'active' : '' }}">Bác sĩ</a></li>
          <li><a href="{{ route('services.index') }}" class="{{ request()->routeIs('services.*') ? 'active' : '' }}">Bảng giá</a></li>
          @auth
            @if (auth()->user()->isStaff())
              <li><a href="{{ route('staff.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i> Hệ thống quản lý</a></li>
            @else
              <li class="dropdown">
                <a href="{{ route('account.dashboard') }}" class="{{ request()->routeIs('account.*') ? 'active' : '' }}">
                  <span><i class="bi bi-person-circle me-1"></i> {{ \Illuminate\Support\Str::limit(auth()->user()->name, 18) }}
                    @if (!empty($unreadNotifications))<span class="badge rounded-pill bg-danger ms-1">{{ $unreadNotifications }}</span>@endif
                  </span>
                  <i class="bi bi-chevron-down toggle-dropdown"></i>
                </a>
                <ul>
                  <li><a href="{{ route('account.dashboard') }}"><i class="bi bi-grid me-2"></i>Tổng quan</a></li>
                  <li><a href="{{ route('account.appointments.index') }}"><i class="bi bi-calendar2-week me-2"></i>Lịch hẹn của tôi</a></li>
                  <li><a href="{{ route('account.visits.index') }}"><i class="bi bi-journal-medical me-2"></i>Lịch sử khám</a></li>
                  <li><a href="{{ route('account.invoices.index') }}"><i class="bi bi-receipt me-2"></i>Hóa đơn</a></li>
                  <li><a href="{{ route('account.patients.index') }}"><i class="bi bi-people me-2"></i>Hồ sơ người thân</a></li>
                  <li><a href="{{ route('account.notifications.index') }}"><i class="bi bi-bell me-2"></i>Thông báo
                      @if (!empty($unreadNotifications))<span class="badge bg-danger ms-auto">{{ $unreadNotifications }}</span>@endif</a></li>
                  <li><a href="{{ route('account.profile.edit') }}"><i class="bi bi-gear me-2"></i>Thông tin tài khoản</a></li>
                  <li>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                      <button type="submit" class="dropdown-logout"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</button>
                    </form>
                  </li>
                </ul>
              </li>
            @endif
          @else
            <li><a href="{{ route('login') }}" class="{{ request()->routeIs('login') ? 'active' : '' }}">Đăng nhập</a></li>
            <li><a href="{{ route('register') }}" class="{{ request()->routeIs('register') ? 'active' : '' }}">Đăng ký</a></li>
          @endauth
        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>

      <a class="btn-booking d-none d-sm-inline-flex" href="{{ route('account.booking.create') }}">
        <i class="bi bi-calendar-plus me-2"></i>Đặt lịch khám
      </a>
    </div>
  </div>

</header>
