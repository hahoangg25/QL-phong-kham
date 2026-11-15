@extends('admin.layouts.app')

@php
  $editing = $user->exists;
  $selected = old('roles', $userRoles);
  $isMe = $editing && $user->id === auth()->id();
@endphp

@section('title', $editing ? 'Sửa nhân viên' : 'Thêm nhân viên')
@section('page_title', $editing ? 'Sửa tài khoản: '.$user->name : 'Thêm tài khoản nhân viên')
@section('breadcrumbs')
  <a href="{{ route('staff.admin.staff.index') }}">Nhân viên</a> / {{ $editing ? 'Sửa' : 'Thêm mới' }}
@endsection

@section('content')
  <form method="POST" action="{{ $editing ? route('staff.admin.staff.update', $user) : route('staff.admin.staff.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header">Thông tin tài khoản</div>
          <div class="card-body row g-3">
            <div class="col-md-8">
              <label class="form-label required">Họ và tên</label>
              <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Mã nhân viên</label>
              <input type="text" name="employee_code" value="{{ old('employee_code', $user->employee_code) }}" class="form-control @error('employee_code') is-invalid @enderror" placeholder="NV010">
            </div>
            <div class="col-md-7">
              <label class="form-label required">Email đăng nhập</label>
              <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Số điện thoại</label>
              <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror">
            </div>
            <div class="col-md-6">
              <label class="form-label {{ $editing ? '' : 'required' }}">Mật khẩu</label>
              <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password"
                     {{ $editing ? '' : 'required' }} placeholder="{{ $editing ? 'Để trống nếu không đổi' : 'Tối thiểu 8 ký tự' }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nhập lại mật khẩu</label>
              <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $user->is_active ?? true)) @disabled($isMe)>
                @if ($isMe)<input type="hidden" name="is_active" value="1">@endif
                <label class="form-check-label" for="isActive">Cho phép đăng nhập</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card mb-4">
          <div class="card-header">Vai trò (RBAC)</div>
          <div class="card-body">
            @foreach ($roles as $value => $label)
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $value }}" id="role_{{ $value }}"
                       @checked(in_array($value, $selected, true)) @disabled($isMe && $value === 'admin')>
                @if ($isMe && $value === 'admin')<input type="hidden" name="roles[]" value="admin">@endif
                <label class="form-check-label" for="role_{{ $value }}">{{ $label }}</label>
              </div>
            @endforeach
            <div class="small text-muted mt-2">
              Quyền chi tiết của từng vai trò được cấu hình ở <a href="{{ route('staff.admin.roles.index') }}">Phân quyền</a>.
              Nhân viên có vai trò Bác sĩ cần thêm hồ sơ chuyên môn để nhận lịch hẹn.
            </div>
            @if ($editing && $user->hasRole('doctor'))
              <hr>
              @if ($user->doctor)
                <a href="{{ route('staff.admin.doctors.edit', $user->doctor) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-badge me-1"></i>Hồ sơ bác sĩ</a>
              @else
                <a href="{{ route('staff.admin.doctors.create', ['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]) }}" class="btn btn-sm btn-warning"><i class="bi bi-person-badge me-1"></i>Tạo hồ sơ bác sĩ</a>
              @endif
            @endif
          </div>
        </div>
        <div class="d-grid gap-2">
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ $editing ? 'Lưu thay đổi' : 'Tạo tài khoản' }}</button>
          <a href="{{ route('staff.admin.staff.index') }}" class="btn btn-light">Hủy</a>
        </div>
      </div>
    </div>
  </form>
@endsection
