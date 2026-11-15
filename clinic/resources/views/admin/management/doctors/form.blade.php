@extends('admin.layouts.app')

@php $editing = $doctor->exists; $u = $doctor->user; @endphp

@section('title', $editing ? 'Sửa hồ sơ bác sĩ' : 'Thêm bác sĩ')
@section('page_title', $editing ? 'Hồ sơ bác sĩ: '.$doctor->display_name : 'Thêm bác sĩ')
@section('breadcrumbs')
  <a href="{{ route('staff.admin.doctors.index') }}">Bác sĩ</a> / {{ $editing ? 'Sửa' : 'Thêm mới' }}
@endsection

@section('content')
  <form method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('staff.admin.doctors.update', $doctor) : route('staff.admin.doctors.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    @if (! $editing && ! empty($linkUserId))
      <input type="hidden" name="link_user_id" value="{{ $linkUserId }}">
    @endif

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card mb-4">
          <div class="card-header"><i class="bi bi-person-lock me-1"></i>Tài khoản đăng nhập</div>
          <div class="card-body row g-3">
            @if (! $editing && ! empty($linkUserId))
              <div class="col-12"><div class="alert alert-info mb-0 small">Hồ sơ chuyên môn sẽ được gắn với tài khoản nhân viên vừa tạo (#{{ $linkUserId }}).</div></div>
            @endif
            <div class="col-md-6">
              <label class="form-label required">Họ và tên</label>
              <input type="text" name="name" value="{{ old('name', $u?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
            </div>
            <div class="col-md-6">
              <label class="form-label required">Email đăng nhập</label>
              <input type="email" name="email" value="{{ old('email', $u?->email) }}" class="form-control @error('email') is-invalid @enderror" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Số điện thoại</label>
              <input type="tel" name="phone" value="{{ old('phone', $u?->phone) }}" class="form-control @error('phone') is-invalid @enderror">
            </div>
            <div class="col-md-4">
              <label class="form-label">Mã nhân viên</label>
              <input type="text" name="employee_code" value="{{ old('employee_code', $u?->employee_code) }}" class="form-control @error('employee_code') is-invalid @enderror">
            </div>
            <div class="col-md-4">
              <label class="form-label {{ $editing || ! empty($linkUserId) ? '' : 'required' }}">Mật khẩu</label>
              <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password"
                     {{ $editing || ! empty($linkUserId) ? '' : 'required' }} placeholder="{{ $editing ? 'Để trống nếu không đổi' : 'Tối thiểu 8 ký tự' }}">
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><i class="bi bi-person-badge me-1"></i>Hồ sơ chuyên môn</div>
          <div class="card-body row g-3">
            <div class="col-md-4">
              <label class="form-label">Học hàm / học vị</label>
              <input type="text" name="title" list="titleList" value="{{ old('title', $doctor->title) }}" class="form-control" placeholder="ThS.BS">
              <datalist id="titleList">
                @foreach ($titles as $t)<option value="{{ $t }}">@endforeach
              </datalist>
            </div>
            <div class="col-md-8">
              <label class="form-label">Chức vụ</label>
              <input type="text" name="position" value="{{ old('position', $doctor->position) }}" class="form-control" placeholder="Trưởng khoa Nội">
            </div>
            <div class="col-md-6">
              <label class="form-label required">Chuyên khoa</label>
              <select name="specialty_id" class="form-select @error('specialty_id') is-invalid @enderror" required>
                <option value="">-- Chọn chuyên khoa --</option>
                @foreach ($specialties as $sp)
                  <option value="{{ $sp->id }}" @selected(old('specialty_id', $doctor->specialty_id) == $sp->id)>{{ $sp->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phòng khám mặc định</label>
              <select name="room_id" class="form-select">
                <option value="">-- Chưa gán --</option>
                @foreach ($rooms as $room)
                  <option value="{{ $room->id }}" @selected(old('room_id', $doctor->room_id) == $room->id)>{{ $room->label() }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label required">Phí khám (đ)</label>
              <input type="number" name="consultation_fee" min="0" step="10000" value="{{ old('consultation_fee', $doctor->consultation_fee) }}" class="form-control @error('consultation_fee') is-invalid @enderror" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Số năm kinh nghiệm</label>
              <input type="number" name="experience_years" min="0" max="80" value="{{ old('experience_years', $doctor->experience_years) }}" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Giới thiệu ngắn</label>
              <input type="text" name="short_bio" maxlength="500" value="{{ old('short_bio', $doctor->short_bio) }}" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Tiểu sử, quá trình công tác</label>
              <textarea name="bio" rows="6" class="form-control">{{ old('bio', $doctor->bio) }}</textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card mb-4">
          <div class="card-header">Ảnh đại diện</div>
          <div class="card-body text-center">
            <img src="{{ $doctor->exists ? $doctor->avatarUrl() : 'https://ui-avatars.com/api/?background=1977cc&color=fff&size=300&name=BS' }}" id="avatarPreview"
                 class="rounded mb-3" style="width:180px;height:200px;object-fit:cover;object-position:top" alt="">
            <input type="file" name="avatar_file" accept="image/*" class="form-control @error('avatar_file') is-invalid @enderror"
                   onchange="if(this.files[0]) document.getElementById('avatarPreview').src = URL.createObjectURL(this.files[0])">
          </div>
        </div>
        <div class="card mb-4">
          <div class="card-body">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $doctor->is_active ?? true))>
              <label class="form-check-label" for="isActive">Đang hoạt động (hiển thị và nhận đặt lịch)</label>
            </div>
            @if ($editing)
              <hr>
              <a href="{{ route('staff.admin.shifts.index', ['doctor_id' => $doctor->id]) }}" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-calendar-week me-1"></i>Cấu hình ca làm việc</a>
            @endif
          </div>
        </div>
        <div class="d-grid gap-2">
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ $editing ? 'Lưu thay đổi' : 'Tạo bác sĩ' }}</button>
          <a href="{{ route('staff.admin.doctors.index') }}" class="btn btn-light">Hủy</a>
        </div>
      </div>
    </div>
  </form>
@endsection
