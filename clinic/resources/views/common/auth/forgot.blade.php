@extends('client.layouts.site')

@section('title', 'Quên mật khẩu')
@section('page_title', 'Quên mật khẩu')
@section('page_subtitle', 'Nhận mã OTP để đặt lại mật khẩu')

@section('content')
  <section class="section pt-4">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
          <div class="card card-soft">
            <div class="card-body p-4 p-md-5">
              <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <label class="form-label">Email hoặc số điện thoại đã đăng ký</label>
                <input type="text" name="login" value="{{ old('login') }}" class="form-control mb-3 @error('login') is-invalid @enderror" required autofocus>
                <button class="btn btn-primary w-100 py-2"><i class="bi bi-send me-2"></i>Gửi mã OTP</button>
              </form>
              <p class="text-center mt-4 mb-0"><a href="{{ route('login') }}"><i class="bi bi-arrow-left me-1"></i>Quay lại đăng nhập</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
