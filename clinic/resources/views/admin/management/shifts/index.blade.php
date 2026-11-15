@extends('admin.layouts.app')

@section('title', 'Ca làm việc')
@section('page_title', 'Cấu hình ca làm việc')
@section('breadcrumbs', 'Quản trị / Lịch làm việc / Ca làm việc')

@section('content')
  <div class="filter-bar d-flex flex-wrap gap-2 align-items-end">
    <form method="GET" class="d-flex gap-2 align-items-end">
      <div>
        <label class="form-label">Bác sĩ</label>
        <select name="doctor_id" class="form-select" onchange="this.form.submit()" style="min-width:320px">
          @foreach ($doctors->groupBy(fn ($d) => $d->specialty?->name) as $spName => $group)
            <optgroup label="{{ $spName }}">
              @foreach ($group as $d)
                <option value="{{ $d->id }}" @selected($doctor?->id === $d->id)>{{ $d->display_name }}{{ $d->is_active ? '' : ' (ngừng)' }}</option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
      </div>
    </form>
    <a href="{{ route('staff.admin.slots.index', ['doctor_id' => $doctor?->id]) }}" class="btn btn-outline-secondary ms-auto"><i class="bi bi-grid-3x3-gap me-1"></i>Xem khung giờ</a>
  </div>

  @if (! $doctor)
    <div class="card"><div class="empty-state"><i class="bi bi-person-badge"></i>Chưa có bác sĩ. Hãy thêm bác sĩ trước.</div></div>
  @else
    <div class="row g-4">
      <div class="col-xl-8">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>Lịch tuần của {{ $doctor->display_name }}</span>
            <small class="text-muted fw-normal">{{ $doctor->specialty?->name }} · Phòng mặc định: {{ $doctor->room?->code ?? '—' }}</small>
          </div>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr><th style="width:110px">Thứ</th><th>Các ca</th></tr>
              </thead>
              <tbody>
                @foreach ($weekdays as $wd => $label)
                  <tr>
                    <td class="fw-semibold">{{ $label }}</td>
                    <td>
                      @forelse ($shifts->get($wd, collect()) as $shift)
                        <div class="d-flex flex-wrap align-items-center gap-2 border rounded px-2 py-1 mb-1 {{ $shift->is_active ? '' : 'bg-light text-muted' }}">
                          <span class="fw-semibold"><i class="bi bi-clock me-1"></i>{{ $shift->timeRange() }}</span>
                          <span class="badge bg-primary-subtle text-primary-emphasis">{{ $shift->slot_minutes }} phút/slot</span>
                          <span class="badge bg-info-subtle text-info-emphasis">tối đa {{ $shift->max_patients_per_slot }} BN/slot</span>
                          <span class="small">Phòng {{ $shift->room?->code ?? $doctor->room?->code ?? '—' }}</span>
                          @if ($shift->effective_from || $shift->effective_to)
                            <span class="small text-muted">Hiệu lực {{ $shift->effective_from?->format('d/m/Y') ?? '...' }} - {{ $shift->effective_to?->format('d/m/Y') ?? '...' }}</span>
                          @endif
                          @unless ($shift->is_active)<span class="badge bg-secondary">Tạm ngưng</span>@endunless
                          <span class="ms-auto d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editShift{{ $shift->id }}" title="Sửa"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('staff.admin.shifts.update', $shift) }}">
                              @csrf @method('PUT')
                              <input type="hidden" name="toggle" value="1">
                              <button class="btn btn-sm btn-light" title="{{ $shift->is_active ? 'Tạm ngưng' : 'Kích hoạt' }}"><i class="bi {{ $shift->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i></button>
                            </form>
                            <form method="POST" action="{{ route('staff.admin.shifts.destroy', $shift) }}" data-confirm="Xóa ca {{ $label }} {{ $shift->timeRange() }}?">
                              @csrf @method('DELETE')
                              <button class="btn btn-sm btn-light text-danger" title="Xóa"><i class="bi bi-trash"></i></button>
                            </form>
                          </span>
                        </div>

                        <div class="modal fade" id="editShift{{ $shift->id }}" tabindex="-1">
                          <div class="modal-dialog">
                            <form method="POST" action="{{ route('staff.admin.shifts.update', $shift) }}" class="modal-content">
                              @csrf @method('PUT')
                              <div class="modal-header"><h5 class="modal-title">Sửa ca {{ $label }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                              <div class="modal-body">
                                @include('admin.management.shifts._fields', ['shift' => $shift])
                              </div>
                              <div class="modal-footer"><button class="btn btn-primary">Lưu</button></div>
                            </form>
                          </div>
                        </div>
                      @empty
                        <span class="text-muted small">Nghỉ</span>
                      @endforelse
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-xl-4">
        <form method="POST" action="{{ route('staff.admin.shifts.store') }}" class="card mb-4">
          @csrf
          <input type="hidden" name="doctor_id" value="{{ $doctor->id }}">
          <div class="card-header"><i class="bi bi-plus-circle me-1"></i>Thêm ca làm việc</div>
          <div class="card-body">
            @include('admin.management.shifts._fields', ['shift' => null])
          </div>
          <div class="card-footer text-end"><button class="btn btn-primary">Thêm ca</button></div>
        </form>

        <form method="POST" action="{{ route('staff.admin.shifts.generate') }}" class="card">
          @csrf
          <div class="card-header"><i class="bi bi-magic me-1"></i>Sinh khung giờ tự động (QT-09)</div>
          <div class="card-body">
            <p class="small text-muted">Hệ thống sinh slot theo lịch tuần cho N ngày tới, bỏ qua ngày bác sĩ nghỉ đã duyệt; slot đã tồn tại được giữ nguyên.</p>
            <div class="row g-2 mb-2">
              <div class="col-5">
                <label class="form-label">Số ngày</label>
                <input type="number" name="days" min="1" max="60" value="{{ $defaultDays }}" class="form-control" required>
              </div>
              <div class="col-7">
                <label class="form-label">Áp dụng cho</label>
                <select name="doctor_id" class="form-select">
                  <option value="{{ $doctor->id }}">Bác sĩ đang chọn</option>
                  <option value="">Tất cả bác sĩ</option>
                </select>
              </div>
            </div>
            <div class="small text-muted mb-3">
              Bác sĩ này hiện có <strong>{{ $upcomingSlots }}</strong> khung giờ từ hôm nay
              @if ($lastSlotDate) (đến {{ $lastSlotDate->format('d/m/Y') }}) @endif.
            </div>
            <button class="btn btn-success w-100"><i class="bi bi-lightning-charge me-1"></i>Sinh slot</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endsection
