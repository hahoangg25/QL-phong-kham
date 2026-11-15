@extends('admin.layouts.app')

@php $editing = $service->exists; @endphp

@section('title', $editing ? 'Sửa dịch vụ' : 'Thêm dịch vụ')
@section('page_title', $editing ? 'Sửa dịch vụ: '.$service->name : 'Thêm dịch vụ')
@section('breadcrumbs')
  <a href="{{ route('staff.admin.services.index') }}">Dịch vụ & bảng giá</a> / {{ $editing ? 'Sửa' : 'Thêm mới' }}
@endsection

@section('content')
  <div class="row">
    <div class="col-lg-8">
      <form method="POST" action="{{ $editing ? route('staff.admin.services.update', $service) : route('staff.admin.services.store') }}" class="card">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-header">Thông tin dịch vụ</div>
        <div class="card-body row g-3">
          <div class="col-md-3">
            <label class="form-label required">Mã dịch vụ</label>
            <input type="text" name="code" value="{{ old('code', $service->code) }}" class="form-control @error('code') is-invalid @enderror" required maxlength="20">
          </div>
          <div class="col-md-9">
            <label class="form-label required">Tên dịch vụ</label>
            <input type="text" name="name" value="{{ old('name', $service->name) }}" class="form-control @error('name') is-invalid @enderror" required>
          </div>
          <div class="col-md-4">
            <label class="form-label required">Loại dịch vụ</label>
            <select name="type" class="form-select" required>
              @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $service->type?->value) === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Chuyên khoa</label>
            <select name="specialty_id" class="form-select">
              <option value="">-- Dùng chung --</option>
              @foreach ($specialties as $sp)
                <option value="{{ $sp->id }}" @selected(old('specialty_id', $service->specialty_id) == $sp->id)>{{ $sp->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label required">Đơn vị tính</label>
            <input type="text" name="unit" value="{{ old('unit', $service->unit) }}" class="form-control" required maxlength="30">
          </div>
          <div class="col-md-4">
            <label class="form-label required">Đơn giá (đ)</label>
            <input type="number" name="price" min="0" step="1000" value="{{ old('price', $service->price) }}" class="form-control @error('price') is-invalid @enderror" required>
          </div>
          <div class="col-12">
            <label class="form-label">Mô tả</label>
            <textarea name="description" rows="3" class="form-control">{{ old('description', $service->description) }}</textarea>
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $service->is_active ?? true))>
              <label class="form-check-label" for="isActive">Đang áp dụng (hiển thị trên bảng giá, cho phép chỉ định)</label>
            </div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
          <a href="{{ route('staff.admin.services.index') }}" class="btn btn-light">Hủy</a>
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu</button>
        </div>
      </form>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body small text-muted">
          <p class="mb-2"><i class="bi bi-info-circle text-primary me-1"></i><strong>Lưu ý về giá</strong></p>
          <p class="mb-2">Giá dịch vụ được chốt tại thời điểm bác sĩ chỉ định, nên thay đổi giá không ảnh hưởng tới các chỉ định và hóa đơn đã lập.</p>
          <p class="mb-0">Phí khám được lấy theo phí khám của từng bác sĩ (mục Quản lý bác sĩ); dịch vụ loại "Khám bệnh" dùng để hiển thị trên bảng giá công khai.</p>
        </div>
      </div>
    </div>
  </div>
@endsection
