@extends('admin.layouts.app')

@section('title', 'Dịch vụ & bảng giá')
@section('page_title', 'Dịch vụ & bảng giá')
@section('breadcrumbs', 'Quản trị / Danh mục / Dịch vụ')

@section('content')
  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-3">
      <label class="form-label">Tìm kiếm</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Mã hoặc tên dịch vụ">
    </div>
    <div class="col-md-3">
      <label class="form-label">Loại dịch vụ</label>
      <select name="type" class="form-select">
        <option value="">Tất cả</option>
        @foreach ($types as $value => $label)
          <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Chuyên khoa</label>
      <select name="specialty_id" class="form-select">
        <option value="">Tất cả</option>
        @foreach ($specialties as $sp)
          <option value="{{ $sp->id }}" @selected(request('specialty_id') == $sp->id)>{{ $sp->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
      <a href="{{ route('staff.admin.services.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Thêm dịch vụ</a>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Mã</th>
            <th>Tên dịch vụ</th>
            <th>Loại</th>
            <th>Chuyên khoa</th>
            <th class="text-end">Đơn giá</th>
            <th>Trạng thái</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($services as $service)
            <tr>
              <td><code>{{ $service->code }}</code></td>
              <td class="fw-semibold">{{ $service->name }}</td>
              <td><i class="bi {{ $service->type->icon() }} text-primary me-1"></i>{{ $service->type->label() }}</td>
              <td>{{ $service->specialty?->name ?? '—' }}</td>
              <td class="text-end fw-semibold">{{ money($service->price) }}<small class="text-muted fw-normal">/{{ $service->unit }}</small></td>
              <td>@include('admin.management.partials.status', ['active' => $service->is_active, 'on' => 'Áp dụng', 'off' => 'Ngừng áp dụng'])</td>
              <td class="text-end text-nowrap">
                <a href="{{ route('staff.admin.services.edit', $service) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('staff.admin.services.destroy', $service) }}" class="d-inline" data-confirm="Xóa dịch vụ {{ $service->name }}?">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-tags"></i>Không có dịch vụ phù hợp.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($services->hasPages())
      <div class="card-footer">{{ $services->links() }}</div>
    @endif
  </div>
@endsection
