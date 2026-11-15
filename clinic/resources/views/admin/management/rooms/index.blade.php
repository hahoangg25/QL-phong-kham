@extends('admin.layouts.app')

@section('title', 'Phòng khám')
@section('page_title', 'Quản lý phòng khám')
@section('breadcrumbs', 'Quản trị / Danh mục / Phòng khám')

@section('content')
  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-4">
      <label class="form-label">Tìm kiếm</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Mã hoặc tên phòng">
    </div>
    <div class="col-md-4">
      <label class="form-label">Chuyên khoa phụ trách</label>
      <select name="specialty_id" class="form-select">
        <option value="">Tất cả</option>
        @foreach ($specialties as $sp)
          <option value="{{ $sp->id }}" @selected(request('specialty_id') == $sp->id)>{{ $sp->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Lọc</button>
      <a href="{{ route('staff.admin.rooms.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Thêm phòng</a>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Mã phòng</th>
            <th>Tên phòng</th>
            <th>Tầng</th>
            <th>Chuyên khoa phụ trách</th>
            <th class="text-center">Bác sĩ</th>
            <th>Trạng thái</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rooms as $room)
            <tr>
              <td><span class="badge bg-primary-subtle text-primary-emphasis fs-6">{{ $room->code }}</span></td>
              <td class="fw-semibold">{{ $room->name }}</td>
              <td>{{ $room->floor ? 'Tầng '.$room->floor : '-' }}</td>
              <td>{{ $room->specialty?->name ?? 'Dùng chung' }}</td>
              <td class="text-center">{{ $doctorCounts[$room->id] ?? 0 }}</td>
              <td>@include('admin.management.partials.status', ['active' => $room->is_active])</td>
              <td class="text-end text-nowrap">
                <a href="{{ route('staff.admin.rooms.edit', $room) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('staff.admin.rooms.destroy', $room) }}" class="d-inline" data-confirm="Xóa phòng {{ $room->code }}?">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-door-open"></i>Chưa có phòng nào.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($rooms->hasPages())
      <div class="card-footer">{{ $rooms->links() }}</div>
    @endif
  </div>
@endsection
