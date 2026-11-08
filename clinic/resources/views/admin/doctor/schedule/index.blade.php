@extends('admin.layouts.app')

@section('title', 'Lịch làm việc')
@section('page_title', 'Lịch làm việc')

@section('content')
  @if (! $doctor)
    <div class="card">
      <div class="empty-state">
        <i class="bi bi-calendar3"></i>
        <h5 class="text-body">Tài khoản chưa có hồ sơ bác sĩ</h5>
        <p class="mb-0">Lịch làm việc chỉ hiển thị cho tài khoản bác sĩ. Quản trị viên xem lịch ở mục Ca làm việc / Khung giờ khám.</p>
      </div>
    </div>
  @else
    <div class="filter-bar d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div class="d-flex align-items-center gap-2">
        <a href="{{ route('staff.schedule.index', ['week' => $weekStart->copy()->subWeek()->toDateString()]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
        <strong>Tuần {{ $weekStart->format('d/m') }} - {{ $weekEnd->format('d/m/Y') }}</strong>
        <a href="{{ route('staff.schedule.index', ['week' => $weekStart->copy()->addWeek()->toDateString()]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
        <a href="{{ route('staff.schedule.index') }}" class="btn btn-sm btn-link">Tuần này</a>
      </div>
      <form method="GET" class="d-flex gap-2">
        <input type="date" name="week" value="{{ $weekStart->toDateString() }}" class="form-control form-control-sm">
        <button class="btn btn-sm btn-primary">Xem</button>
      </form>
    </div>

    <div class="row g-2 mb-3">
      @foreach ($days as $day)
        @php
          $isSelected = $day['date']->isSameDay($selected);
          $isToday = $day['date']->isToday();
        @endphp
        <div class="col-6 col-md-4 col-xl">
          <a href="{{ route('staff.schedule.index', ['week' => $weekStart->toDateString(), 'day' => $day['date']->toDateString()]) }}"
             class="card h-100 text-decoration-none text-body {{ $isSelected ? 'border border-2 border-primary' : '' }}">
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <div class="small text-muted text-capitalize">{{ $day['date']->translatedFormat('l') }}</div>
                  <div class="fw-bold fs-5">{{ $day['date']->format('d/m') }}</div>
                </div>
                @if ($isToday)<span class="badge bg-primary">Hôm nay</span>@endif
              </div>
              @forelse ($day['sessions'] as $name => $s)
                <div class="small mb-1">
                  <span class="badge {{ $name === 'Sáng' ? 'soft-info' : 'soft-purple' }}">{{ $name }}</span>
                  {{ $s['range'] }}
                  <div class="progress mt-1" style="height: 6px" title="{{ $s['booked'] }}/{{ $s['capacity'] }} chỗ">
                    <div class="progress-bar" style="width: {{ $s['capacity'] ? round($s['booked'] / $s['capacity'] * 100) : 0 }}%"></div>
                  </div>
                  <span class="text-muted">{{ $s['booked'] }}/{{ $s['capacity'] }} chỗ đã đặt</span>
                  @if ($s['inactive'])<span class="text-danger"> · {{ $s['inactive'] }} slot khóa</span>@endif
                </div>
              @empty
                <div class="small text-muted">Không có ca làm việc</div>
              @endforelse
              @if ($day['appointment_count'])
                <div class="small mt-2"><i class="bi bi-people me-1"></i>{{ $day['appointment_count'] }} lịch hẹn</div>
              @endif
            </div>
          </a>
        </div>
      @endforeach
    </div>

    <div class="row g-3">
      <div class="col-xl-8">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-calendar-day me-1"></i>Lịch hẹn ngày {{ $selected->format('d/m/Y') }}
            <span class="text-muted fw-normal small">({{ $dayAppointments->count() }})</span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead><tr><th>Giờ</th><th>Mã lịch</th><th>Bệnh nhân</th><th>Triệu chứng</th><th>Nguồn</th><th>Trạng thái</th></tr></thead>
              <tbody>
                @forelse ($dayAppointments as $a)
                  <tr>
                    <td class="fw-semibold">{{ $a->scheduled_at->format('H:i') }}</td>
                    <td><code>{{ $a->code }}</code></td>
                    <td>
                      {{ $a->patient->full_name }}
                      <div class="small text-muted">{{ $a->patient->genderLabel() }}{{ $a->patient->age !== null ? ' · '.$a->patient->age.' tuổi' : '' }}</div>
                    </td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($a->symptoms, 60) }}</td>
                    <td class="small">{{ \App\Models\Scheduling\Appointment::SOURCES[$a->source] ?? $a->source }}</td>
                    <td>{!! status_badge($a->status) !!}</td>
                  </tr>
                @empty
                  <tr><td colspan="6"><div class="empty-state py-4"><i class="bi bi-calendar-x"></i>Không có lịch hẹn.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-xl-4">
        <div class="card mb-3">
          <div class="card-header"><i class="bi bi-calendar-week me-1"></i>Ca làm việc cố định</div>
          <ul class="list-group list-group-flush">
            @forelse ($shifts as $shift)
              <li class="list-group-item d-flex justify-content-between small">
                <span><strong>{{ $shift->weekdayLabel() }}</strong> · {{ $shift->timeRange() }}</span>
                <span class="text-muted">{{ $shift->room?->code ?? $doctor->room?->code }} · {{ $shift->slot_minutes }}′ · {{ $shift->max_patients_per_slot }} BN/slot</span>
              </li>
            @empty
              <li class="list-group-item text-muted small">Chưa được cấu hình ca làm việc.</li>
            @endforelse
          </ul>
        </div>

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-calendar-x me-1"></i>Yêu cầu nghỉ gần đây</span>
            <a href="{{ route('staff.leave.index') }}" class="small fw-normal">Gửi yêu cầu</a>
          </div>
          <ul class="list-group list-group-flush">
            @forelse ($leaves as $leave)
              <li class="list-group-item small d-flex justify-content-between">
                <span>{{ $leave->start_date->format('d/m') }} - {{ $leave->end_date->format('d/m/Y') }}</span>
                {!! status_badge($leave->status) !!}
              </li>
            @empty
              <li class="list-group-item text-muted small">Chưa có yêu cầu nghỉ.</li>
            @endforelse
          </ul>
        </div>
      </div>
    </div>
  @endif
@endsection
