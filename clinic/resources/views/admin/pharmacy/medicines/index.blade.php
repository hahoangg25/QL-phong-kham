@extends('admin.layouts.app')

@section('title', 'Danh mục thuốc')
@section('page_title', 'Danh mục thuốc')
@section('breadcrumbs', 'Dược & kho / Danh mục thuốc')

@section('content')
  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-5">
      <label class="form-label">Tìm kiếm</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên thuốc, hoạt chất hoặc mã">
    </div>
    <div class="col-md-3">
      <label class="form-label">Trạng thái</label>
      <select name="status" class="form-select">
        <option value="">Tất cả</option>
        <option value="active" @selected(request('status') === 'active')>Đang sử dụng</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Ngừng sử dụng</option>
      </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Lọc</button>
      <a href="{{ route('staff.medicines.create') }}" class="btn btn-success ms-auto"><i class="bi bi-plus-lg me-1"></i>Thêm thuốc</a>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Mã</th>
            <th>Tên thuốc</th>
            <th>Hoạt chất</th>
            <th>Đơn vị</th>
            <th class="text-end">Giá bán</th>
            <th class="text-end">Số lượng tồn</th>
            <th>Trạng thái</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($medicines as $m)
            <tr class="{{ $m->is_active ? '' : 'text-muted' }}">
              <td>{{ $m->code }}</td>
              <td>
                <div class="fw-semibold">{{ $m->fullName() }}</div>
                <div class="small text-muted">{{ $m->dosage_form }} · {{ $m->route }}@if ($m->manufacturer) · {{ $m->manufacturer }}@endif</div>
              </td>
              <td class="small">{{ $m->active_ingredient }}</td>
              <td>{{ $m->unit }}</td>
              <td class="text-end">{{ money($m->price) }}</td>
              <td class="text-end">
                <a href="{{ route('staff.inventory.show', $m) }}" class="fw-semibold {{ $m->isLowStock() ? 'text-danger' : '' }}">{{ number_format($m->usableStock()) }}</a>
                @if ($m->isLowStock())
                  <div><span class="badge bg-danger-subtle text-danger">Dưới định mức {{ $m->min_stock }}</span></div>
                @endif
              </td>
              <td>
                @if ($m->is_active)
                  <span class="badge bg-success">Đang sử dụng</span>
                @else
                  <span class="badge bg-secondary">Ngừng sử dụng</span>
                @endif
              </td>
              <td class="text-end text-nowrap">
                <a href="{{ route('staff.medicines.edit', $m) }}" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('staff.medicines.update', $m) }}" class="d-inline">
                  @csrf @method('PUT')
                  <input type="hidden" name="toggle_active" value="1">
                  <button class="btn btn-sm btn-outline-secondary" title="{{ $m->is_active ? 'Ngừng sử dụng' : 'Kích hoạt' }}"><i class="bi {{ $m->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i></button>
                </form>
                <form method="POST" action="{{ route('staff.medicines.destroy', $m) }}" class="d-inline" data-confirm="Xóa thuốc {{ $m->fullName() }}?">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" title="Xóa"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="8"><div class="empty-state"><i class="bi bi-prescription2"></i>Không tìm thấy thuốc nào.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($medicines->hasPages())
      <div class="card-footer">{{ $medicines->links() }}</div>
    @endif
  </div>
@endsection
