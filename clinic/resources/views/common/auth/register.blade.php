@extends('client.layouts.site')

@section('title', 'Đăng ký tài khoản')
@section('page_title', 'Đăng ký tài khoản')
@section('page_subtitle', 'Tạo tài khoản để đặt lịch khám trực tuyến cho bạn và người thân')

@section('content')
  <section class="section pt-4">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-7">
          <div class="card card-soft">
            <div class="card-body p-4 p-md-5">
              <form method="POST" action="{{ route('register') }}">
                @csrf
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
                    <div class="form-text">Mã OTP xác thực sẽ được gửi tới email này.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror" placeholder="09xxxxxxxx" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Ngày sinh <span class="text-danger">*</span></label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ now()->subDay()->toDateString() }}" class="form-control @error('date_of_birth') is-invalid @enderror" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Giới tính <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                      <option value="">-- Chọn --</option>
                      @foreach ($genders as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                    <div class="form-text">Tối thiểu 8 ký tự, gồm chữ và số.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Nhập lại mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                  </div>
                  <div class="col-12">
                    <div class="form-check p-3 rounded bg-light">
                      <input class="form-check-input ms-0 me-2 @error('consent') is-invalid @enderror" type="checkbox" name="consent" id="consent" value="1" @checked(old('consent'))>
                      <label class="form-check-label small" for="consent">
                        Tôi đồng ý cho {{ config('clinic.name') }} thu thập và xử lý dữ liệu cá nhân, dữ liệu sức khỏe của tôi và người thân
                        cho mục đích khám chữa bệnh theo <a href="{{ route('terms') }}" target="_blank">Điều khoản & chính sách bảo vệ dữ liệu cá nhân</a>
                        (Luật Bảo vệ dữ liệu cá nhân 2025).
                      </label>
                    </div>
                  </div>
                </div>
                <button class="btn btn-primary w-100 py-2 mt-4"><i class="bi bi-person-plus me-2"></i>Đăng ký và nhận mã OTP</button>
              </form>
              <p class="text-center mt-4 mb-0">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
