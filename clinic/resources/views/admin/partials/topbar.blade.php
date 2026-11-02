{{-- ================================================================= --}}
{{-- BLOCK CỐ ĐỊNH: TOPBAR THANH TIÊU ĐỀ & THÔNG BÁO NHÂN VIÊN (STAFF) --}}
{{-- ================================================================= --}}
@php $u = auth()->user(); @endphp

<header class="staff-topbar">
  <button class="btn btn-link text-body d-lg-none p-0 me-2" type="button" onclick="document.getElementById('staffSidebar').classList.toggle('show')">
    <i class="bi bi-list fs-3"></i>
  </button>
  <div class="topbar-title">
    <h1>@yield('page_title', 'Tổng quan')</h1>
    @hasSection('breadcrumbs')
      <nav class="small text-muted">@yield('breadcrumbs')</nav>
    @endif
  </div>
  <div class="ms-auto d-flex align-items-center gap-3">
    <span class="text-muted small d-none d-md-inline"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d/m/Y') }}</span>
    <a href="{{ route('staff.notifications.index') }}" class="topbar-icon position-relative" title="Thông báo">
      <i class="bi bi-bell"></i>
      @if (!empty($unreadNotifications))
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unreadNotifications }}</span>
      @endif
    </a>
    <div class="dropdown">
      <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-body" data-bs-toggle="dropdown">
        <img src="{{ $u->avatarUrl() }}" class="rounded-circle me-2" width="34" height="34" alt="">
        <span class="d-none d-md-inline">
          <strong class="d-block lh-1">{{ $u->doctor?->display_name ?? $u->name }}</strong>
          <small class="text-muted">{{ $u->roleLabels() }}</small>
        </span>
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><a class="dropdown-item" href="{{ route('staff.profile.edit') }}"><i class="bi bi-person-gear me-2"></i>Tài khoản</a></li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</header>
