{{-- Khung trang tài khoản bệnh nhân: menu trái + nội dung --}}
@extends('client.layouts.site')

@section('content')
  <section class="section account-section pt-4">
    <div class="container">
      <div class="row g-4">
        {{-- BLOCK CỐ ĐỊNH: SIDEBAR TÀI KHOẢN BỆNH NHÂN --}}
        @include('client.partials.account-sidebar')

        <div class="col-lg-9">
          @hasSection('account_title')
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <h2 class="h4 mb-0">@yield('account_title')</h2>
              <div>@yield('account_actions')</div>
            </div>
          @endif
          @yield('account_content')
        </div>
      </div>
    </div>
  </section>
@endsection
