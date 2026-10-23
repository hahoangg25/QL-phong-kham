<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@hasSection('title')@yield('title') - @endif{{ config('clinic.name') }}</title>
  <meta name="description" content="@yield('description', 'Đặt lịch khám trực tuyến, tra cứu bác sĩ, chuyên khoa và kết quả khám tại '.config('clinic.name'))">

  <link href="{{ asset('assets/img/favicon.png') }}" rel="icon">
  <link href="{{ asset('assets/img/apple-touch-icon.png') }}" rel="apple-touch-icon">

  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@400;500;600;700&family=Lato:wght@400;700&display=swap&subset=vietnamese" rel="stylesheet">

  <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/aos/aos.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/glightbox/css/glightbox.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/vendor/swiper/swiper-bundle.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/css/main.css') }}" rel="stylesheet">
  <link href="{{ asset('css/app.css') }}?v=1" rel="stylesheet">
  @stack('styles')
</head>

<body class="@yield('body_class', 'inner-page')">

  {{-- BLOCK CỐ ĐỊNH: HEADER & NAVIGATION CLIENT --}}
  @include('client.partials.header')

  <main class="main">
    @hasSection('page_title')
      <div class="page-title">
        <div class="heading">
          <div class="container">
            <div class="row d-flex justify-content-center text-center">
              <div class="col-lg-8">
                <h1 class="heading-title">@yield('page_title')</h1>
                @hasSection('page_subtitle')
                  <p class="mb-0">@yield('page_subtitle')</p>
                @endif
              </div>
            </div>
          </div>
        </div>
        <nav class="breadcrumbs">
          <div class="container">
            <ol>
              <li><a href="{{ route('home') }}">Trang chủ</a></li>
              @yield('breadcrumbs')
              <li class="current">@yield('page_title')</li>
            </ol>
          </div>
        </nav>
      </div>
    @endif

    <div class="container flash-container">
      @include('common.partials.flash')
    </div>

    @yield('content')
  </main>

  {{-- BLOCK CỐ ĐỊNH: FOOTER CLIENT --}}
  @include('client.partials.footer')

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader"></div>

  <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/aos/aos.js') }}"></script>
  <script src="{{ asset('assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/purecounter/purecounter_vanilla.js') }}"></script>
  <script src="{{ asset('assets/vendor/swiper/swiper-bundle.min.js') }}"></script>
  <script src="{{ asset('assets/js/main.js') }}"></script>
  <script src="{{ asset('js/app.js') }}?v=1"></script>
  @stack('scripts')
</body>

</html>
