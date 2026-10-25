@extends('client.layouts.account')

@section('title', 'Thông tin tài khoản')
@section('page_title', 'Thông tin tài khoản')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
@endsection

@section('account_title', 'Thông tin tài khoản')

@section('account_content')
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card card-soft h-100">
        <div class="card-header"><i class="bi bi-person-gear me-1"></i>Thông tin cá nhân</div>
        <div class="card-body">
          <form method="POST" action="{{ route('account.profile.update') }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
              <label class="form-label">Họ và tên</label>
              <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Số điện thoại</label>
              <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror" required>
            </div>
            <div class="small text-muted mb-3">
              Ngày đồng ý điều khoản xử lý dữ liệu: {{ $user->consent_at?->format('d/m/Y H:i') ?? '-' }} ·
              Đăng nhập gần nhất: {{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}
            </div>
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu thay đổi</button>
          </form>
          <hr>
          <p class="small text-muted mb-0">Thông tin y tế (ngày sinh, dị ứng, tiền sử...) được quản lý trong
            <a href="{{ route('account.patients.index') }}">Hồ sơ bệnh nhân</a>.</p>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card card-soft h-100">
        <div class="card-header"><i class="bi bi-key me-1"></i>Đổi mật khẩu</div>
        <div class="card-body">
          <form method="POST" action="{{ route('account.profile.password') }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
              <label class="form-label">Mật khẩu hiện tại</label>
              <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Mật khẩu mới</label>
              <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
              <div class="form-text">Tối thiểu 8 ký tự, gồm chữ và số.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Nhập lại mật khẩu mới</label>
              <input type="password" name="password_confirmation" class="form-control" required>
            </div>
            <button class="btn btn-outline-primary"><i class="bi bi-shield-check me-1"></i>Đổi mật khẩu</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
