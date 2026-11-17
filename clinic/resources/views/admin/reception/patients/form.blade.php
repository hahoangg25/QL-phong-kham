@extends('admin.layouts.app')

@php $editing = $patient->exists; @endphp

@section('title', $editing ? 'Sửa bệnh nhân' : 'Thêm bệnh nhân')
@section('page_title', $editing ? 'Cập nhật thông tin bệnh nhân' : 'Thêm bệnh nhân mới')
@section('breadcrumbs')
  <a href="{{ route('staff.patients.index') }}">Bệnh nhân</a> / {{ $editing ? $patient->code : 'Thêm mới' }}
@endsection

@section('content')
  <form method="POST" action="{{ $editing ? route('staff.patients.update', $patient) : route('staff.patients.store') }}" class="card">
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="card-header">{{ $editing ? $patient->code.' - '.$patient->full_name : 'Thông tin bệnh nhân' }}</div>
    <div class="card-body">
      <h6 class="text-muted text-uppercase small mb-3">Thông tin hành chính</h6>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label required">Họ tên</label>
          <input type="text" name="full_name" value="{{ old('full_name', $patient->full_name) }}" class="form-control @error('full_name') is-invalid @enderror" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Ngày sinh</label>
          <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $patient->date_of_birth?->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control @error('date_of_birth') is-invalid @enderror">
        </div>
        <div class="col-md-3">
          <label class="form-label">Giới tính</label>
          <select name="gender" class="form-select">
            <option value="">-- Chọn --</option>
            @foreach ($genders as $value => $label)
              <option value="{{ $value }}" @selected(old('gender', $patient->gender?->value) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Số điện thoại</label>
          <input type="text" name="phone" value="{{ old('phone', $patient->phone) }}" class="form-control @error('phone') is-invalid @enderror" placeholder="09xxxxxxxx">
        </div>
        <div class="col-md-4">
          <label class="form-label">Email</label>
          <input type="email" name="email" value="{{ old('email', $patient->email) }}" class="form-control @error('email') is-invalid @enderror">
        </div>
        <div class="col-md-4">
          <label class="form-label">Số CCCD</label>
          <input type="text" name="id_number" value="{{ old('id_number', $patient->id_number) }}" class="form-control @error('id_number') is-invalid @enderror">
        </div>
        <div class="col-md-4">
          <label class="form-label">Số thẻ BHYT</label>
          <input type="text" name="insurance_number" value="{{ old('insurance_number', $patient->insurance_number) }}" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">Nghề nghiệp</label>
          <input type="text" name="occupation" value="{{ old('occupation', $patient->occupation) }}" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">Nhóm máu</label>
          <select name="blood_type" class="form-select">
            <option value="">-- Chưa rõ --</option>
            @foreach ($bloodTypes as $bt)
              <option value="{{ $bt }}" @selected(old('blood_type', $patient->blood_type) === $bt)>{{ $bt }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label">Địa chỉ</label>
          <input type="text" name="address" value="{{ old('address', $patient->address) }}" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">Liên hệ khẩn cấp</label>
          <input type="text" name="emergency_contact" value="{{ old('emergency_contact', $patient->emergency_contact) }}" class="form-control" placeholder="Tên - SĐT">
        </div>
        @if ($patient->user_id)
          <div class="col-md-4">
            <label class="form-label">Quan hệ với chủ tài khoản</label>
            <select name="relationship" class="form-select">
              @foreach ($relationships as $r)
                <option value="{{ $r }}" @selected(old('relationship', $patient->relationship) === $r)>{{ $r }}</option>
              @endforeach
            </select>
          </div>
        @endif
      </div>

      <h6 class="text-muted text-uppercase small mt-4 mb-3">Tiền sử y tế <span class="text-lowercase fw-normal">(được mã hóa khi lưu - BR-11)</span></h6>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Dị ứng</label>
          <textarea name="allergies" rows="3" class="form-control" placeholder="Phân cách bằng dấu phẩy, vd: Penicillin, Aspirin, hải sản">{{ old('allergies', $patient->allergies) }}</textarea>
          <div class="form-text">Dùng để cảnh báo khi bác sĩ kê đơn (BR-10).</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Bệnh nền / tiền sử</label>
          <textarea name="medical_history" rows="3" class="form-control" placeholder="vd: Tăng huyết áp, đái tháo đường type 2">{{ old('medical_history', $patient->medical_history) }}</textarea>
        </div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
      <a href="{{ $editing ? route('staff.patients.show', $patient) : route('staff.patients.index') }}" class="btn btn-outline-secondary">Hủy</a>
      <button class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ $editing ? 'Lưu thay đổi' : 'Thêm bệnh nhân' }}</button>
    </div>
  </form>
@endsection
