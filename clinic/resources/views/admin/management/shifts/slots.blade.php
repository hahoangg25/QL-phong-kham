@extends('admin.layouts.app')

@section('title', 'Khung giờ khám')
@section('page_title', 'Khung giờ khám')
@section('breadcrumbs', 'Quản trị / Lịch làm việc / Khung giờ')

@section('content')
  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-3">
      <label class="form-label">Ngày</label>
      <div class="input-group">
        <a class="btn btn-outline-secondary" href="{{ route('staff.admin.slots.index', ['date' => $date->copy()->subDay()->toDateString(), 'doctor_id' => request('doctor_id')]) }}"><i class="bi bi-chevron-left"></i></a>
        <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" onchange="this.form.submit()">
        <a class="btn btn-outline-secondary" href="{{ route('staff.admin.slots.index', ['date' => $date->copy()->addDay()->toDateString(), 'doctor_id' => request('doctor_id')]) }}"><i class="bi bi-chevron-right"></i></a>
      </div>
    </div>
    <div class="col-md-5">
      <label class="form-label">Bác sĩ</label>
      <select name="doctor_id" class="form-select" onchange="this.form.submit()">
        <option value="">Tất cả bác sĩ</option>
        @foreach ($doctors as $d)
          <option value="{{ $d->id }}" @selected(request('doctor_id') == $d->id)>{{ $d->display_name }} - {{ $d->specialty?->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4 text-md-end">
      <span class="text-muted small d-block mb-1">{{ ucfirst($date->translatedFormat('l, d/m/Y')) }}</span>
      <a href="{{ route('staff.admin.shifts.index', ['doctor_id' => request('doctor_id')]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-week me-1"></i>Ca làm việc</a>
    </div>
  </form>

  @forelse ($slots as $doctorId => $items)
    @php $d = $items->first()->doctor; @endphp
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ $d->display_name }} <small class="text-muted fw-normal">· {{ $d->specialty?->name }} · Phòng {{ $items->first()->room?->code ?? '—' }}</small></span>
        <small class="text-muted fw-normal">Đã đặt {{ $items->sum('booked_count') }}/{{ $items->sum('capacity') }} chỗ</small>
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
          @foreach ($items as $slot)
            @php
              $full = $slot->booked_count >= $slot->capacity;
              $cls = ! $slot->is_active ? 'bg-light text-muted border-secondary-subtle' : ($full ? 'border-danger-subtle bg-danger-subtle' : ($slot->booked_count ? 'border-warning-subtle bg-warning-subtle' : 'border-success-subtle bg-success-subtle'));
            @endphp
            <form method="POST" action="{{ route('staff.admin.slots.toggle', $slot) }}" class="border rounded p-2 text-center {{ $cls }}" style="min-width:108px"
                  @if ($slot->is_active && $slot->booked_count) data-confirm="Khung giờ đã có {{ $slot->booked_count }} lịch hẹn. Khóa để ngừng nhận đặt mới?" @endif>
              @csrf
              <div class="fw-semibold">{{ $slot->timeLabel() }}</div>
              <div class="small">{{ $slot->booked_count }}/{{ $slot->capacity }} đã đặt</div>
              <button class="btn btn-link btn-sm p-0 text-decoration-none {{ $slot->is_active ? 'text-danger' : 'text-success' }}">
                <i class="bi {{ $slot->is_active ? 'bi-lock' : 'bi-unlock' }}"></i> {{ $slot->is_active ? 'Khóa' : 'Mở' }}
              </button>
            </form>
          @endforeach
        </div>
      </div>
    </div>
  @empty
    <div class="card"><div class="empty-state"><i class="bi bi-calendar-x"></i>Không có khung giờ nào trong ngày này. Kiểm tra ca làm việc hoặc bấm "Sinh slot".</div></div>
  @endforelse

  <div class="small text-muted mt-2">
    <span class="badge bg-success-subtle text-success-emphasis border">Còn trống</span>
    <span class="badge bg-warning-subtle text-warning-emphasis border">Đã có người đặt</span>
    <span class="badge bg-danger-subtle text-danger-emphasis border">Đã đầy</span>
    <span class="badge bg-light text-muted border">Đã khóa</span>
  </div>
@endsection
