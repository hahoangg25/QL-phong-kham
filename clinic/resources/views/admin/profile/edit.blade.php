@extends('admin.layouts.app')

@section('title', 'Tài khoản')
@section('page_title', 'Tài khoản cá nhân')

@section('content')
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card text-center">
        <div class="card-body">
          <img src="{{ $user->avatarUrl() }}" class="rounded-circle mb-3" width="96" height="96" style="object-fit:cover" alt="">
          <h5 class="mb-1">{{ $user->doctor?->display_name ?? $user->name }}</h5>
          <div class="text-muted small mb-2">{{ $user->roleLabels() }}</div>
          @if ($user->employee_code)<span class="badge bg-light text-dark border">Mã NV: {{ $user->employee_code }}</span>@endif
          @if ($user->doctor)
            <div class="small text-muted mt-2">{{ $user->doctor->specialty->name }} · Phí khám {{ money($user->doctor->consultation_fee) }}</div>
          @endif
          <hr>
          <div class="small text-muted">Đăng nhập gần nhất: {{ $user->last_login_at?->format('H:i d/m/Y') ?? '-' }}</div>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <form method="POST" action="{{ route('staff.profile.update') }}" class="card">
        @csrf @method('PUT')
        <div class="card-header">Thông tin liên hệ</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label required">Họ tên</label>
              <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror">
            </div>
            <div class="col-md-6">
              <label class="form-label">Số điện thoại</label>
              <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror">
            </div>
          </div>
          <h6 class="mt-4 mb-3">Đổi mật khẩu <small class="text-muted fw-normal">(để trống nếu không đổi)</small></h6>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Mật khẩu hiện tại</label>
              <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
            </div>
            <div class="col-md-4">
              <label class="form-label">Mật khẩu mới</label>
              <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
            </div>
            <div class="col-md-4">
              <label class="form-label">Nhập lại mật khẩu mới</label>
              <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
            </div>
          </div>
        </div>
        <div class="card-footer text-end">
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu thay đổi</button>
        </div>
      </form>
    </div>
  </div>
@endsection
