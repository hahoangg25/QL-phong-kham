<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@hasSection('title')@yield('title') - @endif Quản lý phòng khám</title>
  <link href="{{ asset('assets/img/favicon.png') }}" rel="icon">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Montserrat:wght@500;600;700&display=swap&subset=vietnamese" rel="stylesheet">
  <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
  <link href="{{ asset('css/staff.css') }}?v=1" rel="stylesheet">
  @stack('styles')
</head>

<body>
  <div class="staff-wrapper">
    {{-- BLOCK CỐ ĐỊNH: SIDEBAR ĐIỀU HƯỚNG NHÂN VIÊN / ADMIN --}}
    @include('admin.partials.sidebar')

    <div class="staff-main">
      {{-- BLOCK CỐ ĐỊNH: TOPBAR TIÊU ĐỀ & THÔNG BÁO NHÂN VIÊN --}}
      @include('admin.partials.topbar')

      <main class="staff-content">
        @include('common.partials.flash')
        @yield('content')
      </main>

      {{-- BLOCK CỐ ĐỊNH: FOOTER NỘI BỘ --}}
      @include('admin.partials.footer')
    </div>
  </div>

  <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('js/app.js') }}?v=1"></script>
  @include('common.partials.realtime')
  @stack('scripts')
</body>

</html>
