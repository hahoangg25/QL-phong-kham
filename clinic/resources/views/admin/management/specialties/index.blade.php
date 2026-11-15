@extends('admin.layouts.app')

@section('title', 'Chuyên khoa')
@section('page_title', 'Quản lý chuyên khoa')
@section('breadcrumbs', 'Quản trị / Danh mục / Chuyên khoa')

@section('content')
  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-5">
      <label class="form-label">Tìm kiếm</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên chuyên khoa">
    </div>
    <div class="col-md-3">
      <label class="form-label">Trạng thái</label>
      <select name="status" class="form-select">
        <option value="">Tất cả</option>
        <option value="active" @selected(request('status') === 'active')>Hoạt động</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Ngừng</option>
      </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Lọc</button>
      <a href="{{ route('staff.admin.specialties.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Thêm chuyên khoa</a>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:60px">TT</th>
            <th>Chuyên khoa</th>
            <th class="text-center">Bác sĩ</th>
            <th class="text-center">Phòng</th>
            <th class="text-center">Dịch vụ</th>
            <th>Từ khóa gợi ý</th>
            <th>Trạng thái</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($specialties as $s)
            <tr>
              <td class="text-muted">{{ $s->sort_order }}</td>
              <td>
                <div class="d-flex align-items-center gap-3">
                  <img src="{{ $s->imageUrl() }}" class="rounded" width="56" height="40" style="object-fit:cover" alt="">
                  <div>
                    <div class="fw-semibold">
                      @if ($s->icon)<i class="fa-solid {{ $s->icon }} text-primary me-1"></i>@endif{{ $s->name }}
                    </div>
                    <small class="text-muted">/chuyen-khoa/{{ $s->slug }}</small>
                  </div>
                </div>
              </td>
              <td class="text-center">{{ $s->doctors_count }}</td>
              <td class="text-center">{{ $s->rooms_count }}</td>
              <td class="text-center">{{ $s->services_count }}</td>
              <td class="small text-muted" style="max-width:260px">{{ \Illuminate\Support\Str::limit($s->keywords, 70) }}</td>
              <td>@include('admin.management.partials.status', ['active' => $s->is_active])</td>
              <td class="text-end text-nowrap">
                <a href="{{ route('specialties.show', $s) }}" target="_blank" class="btn btn-sm btn-light" title="Xem trên website"><i class="bi bi-box-arrow-up-right"></i></a>
                <a href="{{ route('staff.admin.specialties.edit', $s) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('staff.admin.specialties.destroy', $s) }}" class="d-inline" data-confirm="Xóa chuyên khoa {{ $s->name }}? Nếu đã có dữ liệu liên quan, chuyên khoa sẽ được chuyển sang ngừng hoạt động.">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="8"><div class="empty-state"><i class="bi bi-hospital"></i>Chưa có chuyên khoa nào.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($specialties->hasPages())
      <div class="card-footer">{{ $specialties->links() }}</div>
    @endif
  </div>
@endsection

@push('styles')
  <link href="{{ asset('assets/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
@endpush
