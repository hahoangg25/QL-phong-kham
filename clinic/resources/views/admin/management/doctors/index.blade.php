@extends('admin.layouts.app')

@section('title', 'Bác sĩ')
@section('page_title', 'Quản lý bác sĩ')
@section('breadcrumbs', 'Quản trị / Danh mục / Bác sĩ')

@section('content')
  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-4">
      <label class="form-label">Tìm kiếm</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên, email, số điện thoại">
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
    <div class="col-md-2">
      <label class="form-label">Trạng thái</label>
      <select name="status" class="form-select">
        <option value="">Tất cả</option>
        <option value="active" @selected(request('status') === 'active')>Hoạt động</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Ngừng</option>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
      <a href="{{ route('staff.admin.doctors.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Thêm bác sĩ</a>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Bác sĩ</th>
            <th>Chuyên khoa</th>
            <th>Phòng</th>
            <th class="text-end">Phí khám</th>
            <th class="text-center">Kinh nghiệm</th>
            <th>Đánh giá</th>
            <th>Trạng thái</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($doctors as $doctor)
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <img src="{{ $doctor->avatarUrl() }}" width="44" height="44" class="rounded-circle" style="object-fit:cover;object-position:top" alt="">
                  <div>
                    <div class="fw-semibold">{{ $doctor->display_name }}</div>
                    <small class="text-muted">{{ $doctor->user?->email }} · {{ $doctor->user?->phone }}</small>
                  </div>
                </div>
              </td>
              <td>{{ $doctor->specialty?->name }}<br><small class="text-muted">{{ $doctor->position }}</small></td>
              <td>{{ $doctor->room?->code ?? '—' }}</td>
              <td class="text-end fw-semibold">{{ money($doctor->consultation_fee) }}</td>
              <td class="text-center">{{ $doctor->experience_years }} năm</td>
              <td class="text-nowrap">
                @if ($doctor->rating_count)
                  <i class="bi bi-star-fill text-warning"></i> {{ number_format($doctor->rating_avg, 1) }}
                  <small class="text-muted">({{ $doctor->rating_count }})</small>
                @else
                  <small class="text-muted">Chưa có</small>
                @endif
              </td>
              <td>
                @include('admin.management.partials.status', ['active' => $doctor->is_active])
                @if ($doctor->user && ! $doctor->user->is_active)
                  <span class="badge bg-danger-subtle text-danger-emphasis">TK bị khóa</span>
                @endif
              </td>
              <td class="text-end text-nowrap">
                <a href="{{ route('staff.admin.shifts.index', ['doctor_id' => $doctor->id]) }}" class="btn btn-sm btn-light" title="Ca làm việc"><i class="bi bi-calendar-week"></i></a>
                <a href="{{ route('staff.admin.doctors.edit', $doctor) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                @if ($doctor->is_active)
                  <form method="POST" action="{{ route('staff.admin.doctors.destroy', $doctor) }}" class="d-inline" data-confirm="Ngừng hoạt động bác sĩ {{ $doctor->display_name }} và khóa tài khoản đăng nhập?">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" title="Ngừng hoạt động"><i class="bi bi-person-x"></i></button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8"><div class="empty-state"><i class="bi bi-person-badge"></i>Không có bác sĩ phù hợp.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($doctors->hasPages())
      <div class="card-footer">{{ $doctors->links() }}</div>
    @endif
  </div>
@endsection
