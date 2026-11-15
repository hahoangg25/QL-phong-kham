@extends('admin.layouts.app')

@php $editing = $specialty->exists; @endphp

@section('title', $editing ? 'Sửa chuyên khoa' : 'Thêm chuyên khoa')
@section('page_title', $editing ? 'Sửa chuyên khoa: '.$specialty->name : 'Thêm chuyên khoa')
@section('breadcrumbs')
  <a href="{{ route('staff.admin.specialties.index') }}">Chuyên khoa</a> / {{ $editing ? 'Sửa' : 'Thêm mới' }}
@endsection

@section('content')
  <form method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('staff.admin.specialties.update', $specialty) : route('staff.admin.specialties.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header">Thông tin chuyên khoa</div>
          <div class="card-body row g-3">
            <div class="col-md-7">
              <label class="form-label required">Tên chuyên khoa</label>
              <input type="text" name="name" value="{{ old('name', $specialty->name) }}" class="form-control @error('name') is-invalid @enderror" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Đường dẫn (slug)</label>
              <input type="text" name="slug" value="{{ old('slug', $specialty->slug) }}" class="form-control @error('slug') is-invalid @enderror" placeholder="Tự tạo từ tên nếu để trống">
            </div>
            <div class="col-12">
              <label class="form-label">Mô tả ngắn</label>
              <textarea name="short_description" rows="2" class="form-control" maxlength="500">{{ old('short_description', $specialty->short_description) }}</textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Giới thiệu chi tiết</label>
              <textarea name="description" rows="6" class="form-control">{{ old('description', $specialty->description) }}</textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Từ khóa triệu chứng (BN-20)</label>
              <textarea name="keywords" rows="3" class="form-control" placeholder="đau đầu, chóng mặt, mất ngủ">{{ old('keywords', $specialty->keywords) }}</textarea>
              <div class="form-text">Phân cách bằng dấu phẩy. Dùng cho chức năng gợi ý chuyên khoa từ triệu chứng bệnh nhân nhập.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card mb-4">
          <div class="card-header">Hiển thị</div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Biểu tượng (FontAwesome)</label>
              <div class="input-group">
                <span class="input-group-text" style="min-width:46px"><i id="iconPreview" class="fa-solid {{ old('icon', $specialty->icon) ?: 'fa-stethoscope' }} text-primary"></i></span>
                <input type="text" name="icon" id="iconInput" value="{{ old('icon', $specialty->icon) }}" class="form-control" placeholder="fa-heart-pulse">
              </div>
              <div class="form-text">Ví dụ: fa-heart-pulse, fa-tooth, fa-eye, fa-brain, fa-baby, fa-bone.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Ảnh đại diện</label>
              <img src="{{ $specialty->imageUrl() }}" id="imgPreview" class="img-fluid rounded mb-2 w-100" style="max-height:170px;object-fit:cover" alt="">
              <input type="file" name="image_file" accept="image/*" class="form-control @error('image_file') is-invalid @enderror"
                     onchange="if(this.files[0]) document.getElementById('imgPreview').src = URL.createObjectURL(this.files[0])">
            </div>
            <div class="mb-3">
              <label class="form-label">Thứ tự hiển thị</label>
              <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $specialty->sort_order) }}" class="form-control">
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $specialty->is_active ?? true))>
              <label class="form-check-label" for="isActive">Đang hoạt động (hiển thị và nhận đặt lịch)</label>
            </div>
          </div>
        </div>
        <div class="d-grid gap-2">
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ $editing ? 'Lưu thay đổi' : 'Thêm chuyên khoa' }}</button>
          <a href="{{ route('staff.admin.specialties.index') }}" class="btn btn-light">Hủy</a>
        </div>
      </div>
    </div>
  </form>
@endsection

@push('styles')
  <link href="{{ asset('assets/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
@endpush

@push('scripts')
  <script>
    document.getElementById('iconInput').addEventListener('input', function () {
      document.getElementById('iconPreview').className = 'fa-solid ' + (this.value.trim() || 'fa-stethoscope') + ' text-primary';
    });
  </script>
@endpush
