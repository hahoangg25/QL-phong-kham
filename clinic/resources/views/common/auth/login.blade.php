@extends('client.layouts.site')

@section('title', 'Đăng nhập')
@section('page_title', 'Đăng nhập')
@section('page_subtitle', 'Đăng nhập để đặt lịch, xem lịch hẹn và kết quả khám của bạn')

@section('content')
  <section class="section pt-4">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
          <div class="card card-soft">
            <div class="card-body p-4 p-md-5">
              <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                  <label class="form-label">Email hoặc số điện thoại</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="login" value="{{ old('login') }}" class="form-control @error('login') is-invalid @enderror" placeholder="vd: benhnhan@phongkham.test" required autofocus>
                  </div>
                </div>
                <div class="mb-3">
                  <div class="d-flex justify-content-between">
                    <label class="form-label">Mật khẩu</label>
                    <a href="{{ route('password.request') }}" class="small">Quên mật khẩu?</a>
                  </div>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" required>
                  </div>
                </div>
                <div class="form-check mb-4">
                  <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                  <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
                </div>
                <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập</button>
              </form>
              <p class="text-center mt-4 mb-0">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký ngay</a></p>
            </div>
          </div>

          @if (app()->isLocal())
            <div class="card card-soft mt-4">
              <div class="card-body small">
                <strong><i class="bi bi-info-circle me-1"></i>Tài khoản demo</strong> (mật khẩu: <code>password</code>)
                <div class="row mt-2 g-1">
                  @foreach ([
                      'benhnhan@phongkham.test' => 'Bệnh nhân',
                      'admin@phongkham.test' => 'Quản trị',
                      'letan@phongkham.test' => 'Lễ tân',
                      'bs.quang@phongkham.test' => 'Bác sĩ',
                      'ktv@phongkham.test' => 'KTV CLS',
                      'thungan@phongkham.test' => 'Thu ngân',
                      'duocsi@phongkham.test' => 'Dược sĩ',
                  ] as $email => $role)
                    <div class="col-6"><a href="#" class="demo-login text-decoration-none" data-email="{{ $email }}">{{ $role }}</a>: <span class="text-muted">{{ $email }}</span></div>
                  @endforeach
                </div>
              </div>
            </div>
            @push('scripts')
              <script>
                document.querySelectorAll('.demo-login').forEach(a => a.addEventListener('click', e => {
                  e.preventDefault();
                  document.querySelector('[name=login]').value = a.dataset.email;
                  document.querySelector('[name=password]').value = 'password';
                }));
              </script>
            @endpush
          @endif
        </div>
      </div>
    </div>
  </section>
@endsection
