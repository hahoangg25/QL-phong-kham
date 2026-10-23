@extends('client.layouts.site')

@section('title', 'Đặt lại mật khẩu')
@section('page_title', 'Đặt lại mật khẩu')
@section('page_subtitle', 'Nhập mã OTP và mật khẩu mới')

@section('content')
  <section class="section pt-4">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
          <div class="card card-soft">
            <div class="card-body p-4 p-md-5">
              <p class="text-muted small">Mã OTP đã gửi tới <strong>{{ $email }}</strong>.</p>
              <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <div class="mb-3">
                  <label class="form-label">Mã OTP</label>
                  <input type="text" name="otp" inputmode="numeric" maxlength="6" class="form-control otp-input" required autofocus>
                </div>
                <div class="mb-3">
                  <label class="form-label">Mật khẩu mới</label>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-4">
                  <label class="form-label">Nhập lại mật khẩu mới</label>
                  <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100 py-2">Đặt lại mật khẩu</button>
              </form>
              <p class="text-center mt-4 mb-0"><a href="{{ route('password.request') }}">Gửi lại mã</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
