@extends('admin.layouts.app')

@section('title', 'Tổng quan')
@section('page_title', 'Tổng quan')

@section('content')
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
      <h2 class="h5 mb-1">Xin chào, {{ $user->doctor?->display_name ?? $user->name }}!</h2>
      <div class="text-muted small">{{ $user->roleLabels() }} · {{ now()->translatedFormat('l, d/m/Y H:i') }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      @can('appointments.manage')
        <a href="{{ route('staff.appointments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-calendar-plus me-1"></i>Đặt lịch hộ</a>
      @endcan
      @can('reception.manage')
        <a href="{{ route('staff.reception.checkin') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-qr-code-scan me-1"></i>Check-in</a>
        <a href="{{ route('staff.reception.walkin') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Tiếp nhận vãng lai</a>
      @endcan
      @if (isset($myBoard))
        <a href="{{ route('staff.exam.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-clipboard2-pulse me-1"></i>Vào phòng khám</a>
      @endif
    </div>
  </div>

  {{-- Quản trị: chỉ số nhanh --}}
  @isset($kpi)
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
          <div class="stat-icon soft-primary"><i class="bi bi-people"></i></div>
          <div><div class="stat-value">{{ number_format($kpi['visits_today']) }}</div><div class="stat-label">Lượt khám hôm nay</div></div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
          <div class="stat-icon soft-success"><i class="bi bi-cash-stack"></i></div>
          <div><div class="stat-value">{{ money($kpi['revenue_today']) }}</div><div class="stat-label">Doanh thu hôm nay</div></div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
          <div class="stat-icon soft-warning"><i class="bi bi-hourglass-split"></i></div>
          <div><div class="stat-value">{{ number_format($kpi['pending_appointments']) }}</div><div class="stat-label">Lịch chờ xác nhận</div></div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
          <div class="stat-icon soft-danger"><i class="bi bi-person-x"></i></div>
          <div><div class="stat-value">{{ $kpi['no_show_rate'] }}%</div><div class="stat-label">Tỷ lệ vắng mặt 30 ngày</div></div>
        </div>
      </div>
    </div>
    <div class="text-end mb-4 mt-n2">
      <a href="{{ route('staff.admin.reports.index') }}" class="small"><i class="bi bi-graph-up-arrow me-1"></i>Xem báo cáo thống kê chi tiết</a>
    </div>
  @endisset

  <div class="row g-4">
    {{-- Bác sĩ: hàng đợi của tôi --}}
    @isset($myBoard)
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clipboard2-pulse me-1"></i>Hàng đợi của tôi hôm nay</span>
            <a href="{{ route('staff.exam.index') }}" class="small">Mở phòng khám</a>
          </div>
          <div class="card-body">
            <div class="row text-center g-2 mb-3">
              <div class="col-3"><div class="fs-4 fw-bold text-warning">{{ $myBoard['waiting']->count() }}</div><div class="small text-muted">Đang chờ</div></div>
              <div class="col-3"><div class="fs-4 fw-bold text-primary">{{ $myBoard['in_progress']->count() }}</div><div class="small text-muted">Đang khám</div></div>
              <div class="col-3"><div class="fs-4 fw-bold text-purple">{{ $myBoard['awaiting_results']->count() }}</div><div class="small text-muted">Chờ CLS</div></div>
              <div class="col-3"><div class="fs-4 fw-bold text-success">{{ $myBoard['completed']->count() }}</div><div class="small text-muted">Đã xong</div></div>
            </div>
            @forelse ($myBoard['waiting']->take(5) as $v)
              <div class="queue-item {{ $v->is_priority ? 'priority' : '' }}">
                <div class="queue-no">{{ $v->queue_number }}</div>
                <div class="flex-grow-1">
                  <div class="fw-semibold">{{ $v->patient->full_name }}
                    @if ($v->is_priority)<span class="badge bg-warning text-dark ms-1">Ưu tiên</span>@endif</div>
                  <div class="small text-muted">{{ $v->patient->summary() }}{{ $v->reason ? ' · '.$v->reason : '' }}</div>
                </div>
              </div>
            @empty
              <div class="empty-state py-3"><i class="bi bi-cup-hot"></i>Chưa có bệnh nhân đang chờ.</div>
            @endforelse
          </div>
        </div>
      </div>
    @endisset

    {{-- Lễ tân: lịch hẹn hôm nay --}}
    @isset($todayStatus)
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-calendar2-week me-1"></i>Lịch hẹn hôm nay</span>
            <a href="{{ route('staff.appointments.index') }}" class="small">Xem tất cả</a>
          </div>
          <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
              @foreach (\App\Enums\AppointmentStatus::cases() as $s)
                @if (($todayStatus[$s->value] ?? 0) > 0)
                  <a href="{{ route('staff.appointments.index', ['status' => $s->value]) }}" class="text-decoration-none">
                    <span class="badge badge-status bg-{{ $s->color() }}">{{ $s->label() }}: {{ $todayStatus[$s->value] }}</span>
                  </a>
                @endif
              @endforeach
              @if ($todayStatus->sum() == 0)
                <span class="text-muted small">Hôm nay chưa có lịch hẹn.</span>
              @endif
            </div>

            <h6 class="small text-uppercase text-muted mb-2">Lịch chờ xác nhận gần nhất</h6>
            @forelse ($pendingList as $a)
              <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                <div class="flex-grow-1">
                  <a href="{{ route('staff.appointments.show', $a) }}" class="fw-semibold">{{ $a->patient->full_name }}</a>
                  <div class="small text-muted">{{ $a->scheduled_at->format('H:i d/m') }} · {{ $a->doctor->display_name }} · {{ $a->specialty->name }}</div>
                </div>
                @can('appointments.manage')
                  <form method="POST" action="{{ route('staff.appointments.confirm', $a) }}">@csrf
                    <button class="btn btn-sm btn-success" title="Xác nhận"><i class="bi bi-check-lg"></i></button>
                  </form>
                  <form method="POST" action="{{ route('staff.appointments.reject', $a) }}" data-prompt="Lý do từ chối lịch hẹn {{ $a->code }}:" data-prompt-field="reason">@csrf
                    <button class="btn btn-sm btn-outline-danger" title="Từ chối"><i class="bi bi-x-lg"></i></button>
                  </form>
                @endcan
              </div>
            @empty
              <div class="text-muted small">Không có lịch chờ xác nhận.</div>
            @endforelse
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ol me-1"></i>Hàng đợi theo bác sĩ</span>
            @can('reception.manage')<a href="{{ route('staff.queue.index') }}" class="small">Điều phối hàng đợi</a>@endcan
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead><tr><th>Bác sĩ</th><th>Phòng</th><th class="text-center">Chờ</th><th class="text-center">Đang khám</th><th class="text-center">Xong</th></tr></thead>
              <tbody>
                @forelse ($doctorQueues as $row)
                  <tr>
                    <td>
                      @can('reception.manage')
                        <a href="{{ route('staff.queue.index', ['doctor_id' => $row['doctor']->id]) }}">{{ $row['doctor']->display_name }}</a>
                      @else
                        {{ $row['doctor']->display_name }}
                      @endcan
                      <div class="small text-muted">{{ $row['doctor']->specialty->name }}</div>
                    </td>
                    <td>{{ $row['doctor']->room?->code ?? '-' }}</td>
                    <td class="text-center"><span class="badge bg-warning text-dark">{{ $row['waiting'] }}</span></td>
                    <td class="text-center">{{ $row['in_progress'] }}</td>
                    <td class="text-center">{{ $row['done'] }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="empty-state"><i class="bi bi-inbox"></i>Chưa có bệnh nhân check-in hôm nay.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endisset

    {{-- Cận lâm sàng --}}
    @isset($labPending)
      <div class="col-md-6 col-xl-4">
        <div class="card h-100">
          <div class="card-header"><i class="bi bi-droplet-half me-1"></i>Cận lâm sàng</div>
          <div class="card-body">
            <div class="d-flex gap-4 mb-3">
              <div><div class="fs-3 fw-bold text-warning">{{ $labPending }}</div><div class="small text-muted">Chờ thực hiện</div></div>
              <div><div class="fs-3 fw-bold text-primary">{{ $labInProgress }}</div><div class="small text-muted">Đang thực hiện</div></div>
            </div>
            <a href="{{ route('staff.lab.index') }}" class="btn btn-sm btn-outline-primary">Danh sách chỉ định</a>
          </div>
        </div>
      </div>
    @endisset

    {{-- Thu ngân --}}
    @isset($unpaidCount)
      <div class="col-md-6 col-xl-4">
        <div class="card h-100">
          <div class="card-header"><i class="bi bi-cash-coin me-1"></i>Thu ngân</div>
          <div class="card-body">
            <div class="d-flex gap-4 mb-3">
              <div><div class="fs-3 fw-bold text-warning">{{ $unpaidCount }}</div><div class="small text-muted">Hóa đơn chưa thu</div></div>
              <div><div class="fs-5 fw-bold">{{ money($unpaidTotal) }}</div><div class="small text-muted">Tổng còn phải thu</div></div>
            </div>
            <a href="{{ route('staff.cashier.index') }}" class="btn btn-sm btn-outline-primary">Mở quầy thu ngân</a>
            <a href="{{ route('staff.cashier.report') }}" class="btn btn-sm btn-link">Báo cáo ca</a>
          </div>
        </div>
      </div>
    @endisset

    {{-- Dược & kho --}}
    @isset($toDispense)
      <div class="col-md-6 col-xl-4">
        <div class="card h-100">
          <div class="card-header"><i class="bi bi-capsule me-1"></i>Dược & kho</div>
          <div class="card-body">
            <div class="d-flex flex-wrap gap-4 mb-3">
              <div><div class="fs-3 fw-bold text-primary">{{ $toDispense }}</div><div class="small text-muted">Đơn chờ cấp thuốc</div></div>
              <div><div class="fs-3 fw-bold text-warning">{{ $alerts['low'] }}</div><div class="small text-muted">Dưới định mức</div></div>
              <div><div class="fs-3 fw-bold text-danger">{{ $alerts['nearExpiry'] + $alerts['expired'] }}</div><div class="small text-muted">Lô sắp/đã hết hạn</div></div>
            </div>
            @can('pharmacy.dispense')<a href="{{ route('staff.pharmacy.index') }}" class="btn btn-sm btn-outline-primary">Cấp phát thuốc</a>@endcan
            @can('inventory.manage')<a href="{{ route('staff.inventory.index') }}" class="btn btn-sm btn-link">Tồn kho & cảnh báo</a>@endcan
          </div>
        </div>
      </div>
    @endisset
  </div>
@endsection
