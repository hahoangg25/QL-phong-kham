@extends('client.layouts.account')

@php $editing = $patient->exists; @endphp

@section('title', $editing ? 'Sửa hồ sơ' : 'Thêm hồ sơ')
@section('page_title', $editing ? 'Cập nhật hồ sơ bệnh nhân' : 'Thêm hồ sơ bệnh nhân')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
  <li><a href="{{ route('account.patients.index') }}">Hồ sơ bệnh nhân</a></li>
@endsection

@section('account_title', $editing ? 'Hồ sơ '.$patient->full_name : 'Thêm hồ sơ người thân')

@section('account_content')
  <form method="POST" action="{{ $editing ? route('account.patients.update', $patient) : route('account.patients.store') }}" class="card card-soft">
    @csrf
    @if ($editing) @method('PUT') @endif
    <input type="hidden" name="redirect_to" value="{{ request('redirect_to') }}">
    <div class="card-header"><i class="bi bi-person-vcard me-1"></i>Thông tin hành chính</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
          <input type="text" name="full_name" value="{{ old('full_name', $patient->full_name) }}" class="form-control @error('full_name') is-invalid @enderror" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Mối quan hệ <span class="text-danger">*</span></label>
          <select name="relationship" class="form-select @error('relationship') is-invalid @enderror" required>
            @foreach ($relationships as $r)
              <option value="{{ $r }}" @selected(old('relationship', $patient->relationship) === $r)>{{ $r }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Giới tính <span class="text-danger">*</span></label>
          <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
            <option value="">-- Chọn --</option>
            @foreach ($genders as $value => $label)
              <option value="{{ $value }}" @selected(old('gender', $patient->gender?->value) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Ngày sinh <span class="text-danger">*</span></label>
          <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $patient->date_of_birth?->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control @error('date_of_birth') is-invalid @enderror" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Số điện thoại</label>
          <input type="tel" name="phone" value="{{ old('phone', $patient->phone) }}" class="form-control @error('phone') is-invalid @enderror">
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
          <label class="form-label">Nhóm máu</label>
          <select name="blood_type" class="form-select">
            <option value="">Chưa rõ</option>
            @foreach (['A', 'B', 'AB', 'O'] as $bt)
              <option value="{{ $bt }}" @selected(old('blood_type', $patient->blood_type) === $bt)>{{ $bt }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label">Địa chỉ</label>
          <input type="text" name="address" value="{{ old('address', $patient->address) }}" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">Người liên hệ khẩn cấp</label>
          <input type="text" name="emergency_contact" value="{{ old('emergency_contact', $patient->emergency_contact) }}" class="form-control" placeholder="Tên - SĐT">
        </div>
      </div>
    </div>
    <div class="card-header border-top"><i class="bi bi-shield-lock me-1"></i>Thông tin y tế <small class="text-muted fw-normal">(được mã hóa khi lưu - BR-11)</small></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Dị ứng</label>
          <textarea name="allergies" rows="3" class="form-control" placeholder="vd: Penicillin, hải sản (phân cách bằng dấu phẩy)">{{ old('allergies', $patient->allergies) }}</textarea>
          <div class="form-text">Bác sĩ sẽ được cảnh báo khi kê thuốc có thành phần gây dị ứng.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Tiền sử bệnh</label>
          <textarea name="medical_history" rows="3" class="form-control" placeholder="vd: Tăng huyết áp, đái tháo đường...">{{ old('medical_history', $patient->medical_history) }}</textarea>
        </div>
      </div>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ $editing ? 'Lưu thay đổi' : 'Thêm hồ sơ' }}</button>
      <a href="{{ route('account.patients.index') }}" class="btn btn-light">Hủy</a>
    </div>
  </form>
@endsection
