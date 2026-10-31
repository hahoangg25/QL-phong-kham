@extends('admin.layouts.app')

@section('title', 'Lịch hẹn')
@section('page_title', 'Danh sách lịch hẹn')
@section('breadcrumbs', 'Lễ tân / Lịch hẹn')

@section('content')
  <form method="GET" class="filter-bar">
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label">Ngày khám</label>
        <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Chuyên khoa</label>
        <select name="specialty_id" class="form-select form-select-sm">
          <option value="">Tất cả</option>
          @foreach ($specialties as $s)
            <option value="{{ $s->id }}" @selected(request('specialty_id') == $s->id)>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Bác sĩ</label>
        <select name="doctor_id" class="form-select form-select-sm">
          <option value="">Tất cả</option>
          @foreach ($doctors as $d)
            <option value="{{ $d->id }}" @selected(request('doctor_id') == $d->id)>{{ $d->display_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Trạng thái</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">Tất cả</option>
          @foreach ($statuses as $value => $label)
            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-8 col-md-2">
        <label class="form-label">Tìm kiếm</label>
        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Mã lịch, tên, SĐT">
      </div>
      <div class="col-4 col-md-2 d-flex gap-1">
        <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Lọc</button>
        <a href="{{ route('staff.appointments.index') }}" class="btn btn-sm btn-outline-secondary" title="Hôm nay"><i class="bi bi-arrow-counterclockwise"></i></a>
      </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
      <span class="small text-muted me-1">{{ $date ? 'Ngày '.\Carbon\Carbon::parse($date)->format('d/m/Y') : 'Từ hôm nay trở đi' }}:</span>
      @foreach (\App\Enums\AppointmentStatus::cases() as $s)
        @if (($statusCounts[$s->value] ?? 0) > 0)
          <a href="{{ request()->fullUrlWithQuery(['status' => $s->value, 'page' => null]) }}" class="text-decoration-none">
            <span class="badge badge-status bg-{{ $s->color() }} {{ request('status') === $s->value ? 'border border-2 border-dark' : '' }}">{{ $s->label() }}: {{ $statusCounts[$s->value] }}</span>
          </a>
        @endif
      @endforeach
      <span class="small text-muted">Tổng: {{ $statusCounts->sum() }}</span>
      @can('appointments.manage')
        <a href="{{ route('staff.appointments.create') }}" class="btn btn-sm btn-success ms-auto"><i class="bi bi-calendar-plus me-1"></i>Đặt lịch hộ</a>
      @endcan
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Giờ khám</th><th>Mã lịch</th><th>Bệnh nhân</th><th>Bác sĩ / Chuyên khoa</th><th>Nguồn</th><th>Trạng thái</th><th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($appointments as $a)
            @php
              $isToday = $a->appointment_date->isToday();
              $late = $isToday && now()->gt($a->scheduled_at->copy()->addMinutes((int) setting('late_checkin_minutes')));
            @endphp
            <tr>
              <td>
                <div class="fw-semibold">{{ $a->scheduled_at->format('H:i') }}</div>
                <div class="small text-muted">{{ $a->scheduled_at->format('d/m/Y') }}</div>
              </td>
              <td><a href="{{ route('staff.appointments.show', $a) }}" class="fw-semibold">{{ $a->code }}</a></td>
              <td>
                <div>{{ $a->patient->full_name }}</div>
                <div class="small text-muted">{{ $a->patient->phone }} · {{ $a->patient->code }}</div>
              </td>
              <td>
                <div>{{ $a->doctor->display_name }}</div>
                <div class="small text-muted">{{ $a->specialty->name }}</div>
              </td>
              <td><span class="small">{{ \App\Models\Scheduling\Appointment::SOURCES[$a->source] ?? $a->source }}</span></td>
              <td>
                {!! status_badge($a->status) !!}
                @if ($late && $a->status->isCancellable())<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Trễ</span>@endif
              </td>
              <td class="text-end text-nowrap">
                @can('appointments.manage')
                  @if ($a->status === \App\Enums\AppointmentStatus::Pending)
                    <form method="POST" action="{{ route('staff.appointments.confirm', $a) }}" class="d-inline">@csrf
                      <button class="btn btn-sm btn-success" title="Xác nhận"><i class="bi bi-check-lg"></i></button>
                    </form>
                    <form method="POST" action="{{ route('staff.appointments.reject', $a) }}" class="d-inline" data-prompt="Lý do từ chối lịch hẹn {{ $a->code }}:" data-prompt-field="reason">@csrf
                      <button class="btn btn-sm btn-outline-danger" title="Từ chối"><i class="bi bi-x-lg"></i></button>
                    </form>
                  @endif
                @endcan
                @can('reception.manage')
                  @if ($isToday && $a->status->isCancellable())
                    <form method="POST" action="{{ route('staff.reception.checkin.store', $a) }}" class="d-inline">@csrf
                      <button class="btn btn-sm btn-primary" title="Check-in"><i class="bi bi-box-arrow-in-right"></i> Check-in</button>
                    </form>
                  @endif
                @endcan
                <div class="btn-group">
                  <a href="{{ route('staff.appointments.show', $a) }}" class="btn btn-sm btn-outline-secondary" title="Chi tiết"><i class="bi bi-eye"></i></a>
                  @can('appointments.manage')
                    @if ($a->status->isCancellable())
                      <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('staff.appointments.reschedule', $a) }}"><i class="bi bi-arrow-repeat me-2"></i>Đổi lịch</a></li>
                        <li>
                          <button type="button" class="dropdown-item text-danger js-cancel"
                                  data-action="{{ route('staff.appointments.cancel', $a) }}" data-code="{{ $a->code }}"
                                  data-late="{{ $a->status === \App\Enums\AppointmentStatus::Confirmed && $a->isLateCancellationNow() ? 1 : 0 }}">
                            <i class="bi bi-calendar-x me-2"></i>Hủy lịch
                          </button>
                        </li>
                      </ul>
                    @endif
                  @endcan
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="empty-state"><i class="bi bi-calendar2-x"></i>Không có lịch hẹn phù hợp.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($appointments->hasPages())
      <div class="card-footer">{{ $appointments->links() }}</div>
    @endif
  </div>

  @include('admin.reception.appointments._cancel_modal')
@endsection
