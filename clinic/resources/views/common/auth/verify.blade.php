@extends('client.layouts.site')

@section('title', 'Xác thực tài khoản')
@section('page_title', 'Xác thực tài khoản')
@section('page_subtitle', 'Nhập mã OTP gồm 6 chữ số đã gửi tới email của bạn')

@section('content')
  <section class="section pt-4">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
          <div class="card card-soft text-center">
            <div class="card-body p-4 p-md-5">
              <div class="icon-circle mx-auto mb-3"><i class="bi bi-envelope-check"></i></div>
              <p>Mã OTP đã được gửi tới <strong>{{ $user->email }}</strong>.<br>Mã có hiệu lực trong {{ config('clinic.otp_ttl_minutes') }} phút.</p>
              <form method="POST" action="{{ route('verify.submit') }}">
                @csrf
                <input type="text" name="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="form-control otp-input mx-auto mb-3" placeholder="______" required autofocus>
                <button class="btn btn-primary w-100 py-2">Xác thực</button>
              </form>
              <form method="POST" action="{{ route('verify.resend') }}" class="mt-3">
                @csrf
                <button class="btn btn-link">Chưa nhận được mã? Gửi lại</button>
              </form>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-link text-muted small">Đăng xuất</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
