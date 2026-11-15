@extends('admin.layouts.app')

@php $editing = $room->exists; @endphp

@section('title', $editing ? 'Sửa phòng' : 'Thêm phòng')
@section('page_title', $editing ? 'Sửa phòng '.$room->code : 'Thêm phòng khám')
@section('breadcrumbs')
  <a href="{{ route('staff.admin.rooms.index') }}">Phòng khám</a> / {{ $editing ? 'Sửa' : 'Thêm mới' }}
@endsection

@section('content')
  <div class="row">
    <div class="col-lg-7">
      <form method="POST" action="{{ $editing ? route('staff.admin.rooms.update', $room) : route('staff.admin.rooms.store') }}" class="card">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-header">Thông tin phòng</div>
        <div class="card-body row g-3">
          <div class="col-md-4">
            <label class="form-label required">Mã phòng</label>
            <input type="text" name="code" value="{{ old('code', $room->code) }}" class="form-control @error('code') is-invalid @enderror" required maxlength="20">
          </div>
          <div class="col-md-8">
            <label class="form-label required">Tên phòng</label>
            <input type="text" name="name" value="{{ old('name', $room->name) }}" class="form-control @error('name') is-invalid @enderror" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Tầng</label>
            <input type="text" name="floor" value="{{ old('floor', $room->floor) }}" class="form-control">
          </div>
          <div class="col-md-8">
            <label class="form-label">Chuyên khoa phụ trách</label>
            <select name="specialty_id" class="form-select">
              <option value="">-- Dùng chung (xét nghiệm, CĐHA...) --</option>
              @foreach ($specialties as $sp)
                <option value="{{ $sp->id }}" @selected(old('specialty_id', $room->specialty_id) == $sp->id)>{{ $sp->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $room->is_active ?? true))>
              <label class="form-check-label" for="isActive">Đang hoạt động</label>
            </div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
          <a href="{{ route('staff.admin.rooms.index') }}" class="btn btn-light">Hủy</a>
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu</button>
        </div>
      </form>
    </div>
  </div>
@endsection
