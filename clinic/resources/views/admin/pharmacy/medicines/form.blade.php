@extends('admin.layouts.app')

@php $editing = $medicine->exists; @endphp

@section('title', $editing ? 'Sửa thuốc' : 'Thêm thuốc')
@section('page_title', $editing ? 'Sửa thuốc: '.$medicine->fullName() : 'Thêm thuốc mới')
@section('breadcrumbs')
  <a href="{{ route('staff.medicines.index') }}">Danh mục thuốc</a> / {{ $editing ? 'Sửa' : 'Thêm mới' }}
@endsection

@section('content')
  <div class="row justify-content-center">
    <div class="col-xl-9">
      <form method="POST" action="{{ $editing ? route('staff.medicines.update', $medicine) : route('staff.medicines.store') }}" class="card">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-header">Thông tin thuốc</div>
        <div class="card-body row g-3">
          <div class="col-md-3">
            <label class="form-label required">Mã thuốc</label>
            <input type="text" name="code" value="{{ old('code', $medicine->code) }}" class="form-control @error('code') is-invalid @enderror" maxlength="20" required>
          </div>
          <div class="col-md-5">
            <label class="form-label required">Tên thuốc (biệt dược)</label>
            <input type="text" name="name" value="{{ old('name', $medicine->name) }}" class="form-control @error('name') is-invalid @enderror" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Hàm lượng</label>
            <input type="text" name="strength" value="{{ old('strength', $medicine->strength) }}" class="form-control" placeholder="VD: 500mg">
          </div>
          <div class="col-md-6">
            <label class="form-label">Hoạt chất</label>
            <input type="text" name="active_ingredient" value="{{ old('active_ingredient', $medicine->active_ingredient) }}" class="form-control" placeholder="Dùng để cảnh báo dị ứng khi kê đơn">
          </div>
          <div class="col-md-6">
            <label class="form-label">Dạng bào chế</label>
            <input type="text" name="dosage_form" value="{{ old('dosage_form', $medicine->dosage_form) }}" class="form-control" placeholder="VD: Viên nén bao phim">
          </div>
          <div class="col-md-3">
            <label class="form-label required">Đơn vị tính</label>
            <input type="text" name="unit" value="{{ old('unit', $medicine->unit) }}" class="form-control @error('unit') is-invalid @enderror" list="unitList" required>
            <datalist id="unitList">@foreach ($units as $u)<option value="{{ $u }}">@endforeach</datalist>
          </div>
          <div class="col-md-3">
            <label class="form-label required">Đường dùng</label>
            <input type="text" name="route" value="{{ old('route', $medicine->route) }}" class="form-control @error('route') is-invalid @enderror" list="routeList" required>
            <datalist id="routeList">@foreach ($routes as $r)<option value="{{ $r }}">@endforeach</datalist>
          </div>
          <div class="col-md-3">
            <label class="form-label required">Giá bán (đ)</label>
            <input type="number" name="price" value="{{ old('price', $medicine->price) }}" min="0" step="100" class="form-control @error('price') is-invalid @enderror" required>
          </div>
          <div class="col-md-3">
            <label class="form-label required">Định mức tồn tối thiểu</label>
            <input type="number" name="min_stock" value="{{ old('min_stock', $medicine->min_stock ?? 0) }}" min="0" class="form-control @error('min_stock') is-invalid @enderror" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Hãng sản xuất</label>
            <input type="text" name="manufacturer" value="{{ old('manufacturer', $medicine->manufacturer) }}" class="form-control">
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $medicine->is_active))>
              <label class="form-check-label" for="is_active">Đang sử dụng (cho phép kê đơn)</label>
            </div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu</button>
          <a href="{{ route('staff.medicines.index') }}" class="btn btn-outline-secondary">Hủy</a>
        </div>
      </form>
    </div>
  </div>
@endsection
