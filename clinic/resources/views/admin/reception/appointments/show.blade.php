@extends('admin.layouts.app')

@section('title', 'Lịch hẹn '.$appointment->code)
@section('page_title', 'Lịch hẹn '.$appointment->code)
@section('breadcrumbs')
  <a href="{{ route('staff.appointments.index') }}">Lịch hẹn</a> / {{ $appointment->code }}
@endsection

@section('content')
  @php
    $a = $appointment;
    $isToday = $a->appointment_date->isToday();
  @endphp

  <div class="d-flex flex-wrap gap-2 mb-3">
    @can('appointments.manage')
      @if ($a->status === \App\Enums\AppointmentStatus::Pending)
        <form method="POST" action="{{ route('staff.appointments.confirm', $a) }}">@csrf
          <button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Xác nhận</button>
        </form>
        <form method="POST" action="{{ route('staff.appointments.reject', $a) }}" data-prompt="Lý do từ chối lịch hẹn {{ $a->code }}:" data-prompt-field="reason">@csrf
          <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg me-1"></i>Từ chối</button>
        </form>
      @endif
    @endcan
    @can('reception.manage')
      @if ($isToday && $a->status->isCancellable())
        <form method="POST" action="{{ route('staff.reception.checkin.store', $a) }}">@csrf
          <button class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>Check-in</button>
        </form>
      @endif
      @if ($a->visit)
        <a href="{{ route('staff.queue.ticket', $a->visit) }}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer me-1"></i>In phiếu STT {{ $a->visit->queue_number }}</a>
      @endif
    @endcan
    @can('appointments.manage')
      @if ($a->status->isCancellable())
        <a href="{{ route('staff.appointments.reschedule', $a) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Đổi lịch</a>
        <button type="button" class="btn btn-outline-danger btn-sm js-cancel" data-action="{{ route('staff.appointments.cancel', $a) }}" data-code="{{ $a->code }}"
                data-late="{{ $a->status === \App\Enums\AppointmentStatus::Confirmed && $a->isLateCancellationNow() ? 1 : 0 }}">
          <i class="bi bi-calendar-x me-1"></i>Hủy lịch
        </button>
      @endif
    @endcan
    @if ($a->visit)
      @can('medical.view')
        <a href="{{ route('staff.records.show', $a->visit) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-medical me-1"></i>Xem lượt khám</a>
      @endcan
    @endif
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Thông tin lịch hẹn</span>
          {!! status_badge($a->status) !!}
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="small text-muted">Thời gian khám</div>
              <div class="fw-semibold fs-5">{{ $a->scheduled_at->format('H:i') }} <span class="fw-normal fs-6">{{ $a->scheduled_at->translatedFormat('l, d/m/Y') }}</span></div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Bác sĩ</div>
              <div class="fw-semibold">{{ $a->doctor->display_name }}</div>
              <div class="small text-muted">{{ $a->specialty->name }} · Phòng {{ ($a->timeSlot?->room ?? $a->doctor->room)?->code ?? '-' }} · Phí khám {{ money($a->doctor->consultation_fee) }}</div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Nguồn đặt</div>
              <div>{{ \App\Models\Scheduling\Appointment::SOURCES[$a->source] ?? $a->source }}
                @if ($a->bookedBy)<span class="text-muted small">- bởi {{ $a->bookedBy->name }}</span>@endif</div>
              <div class="small text-muted">Tạo lúc {{ $a->created_at->format('H:i d/m/Y') }}</div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Thanh toán trước</div>
              <div>{{ money($a->prepaid_amount) }}
                @if ($a->deposit_required)<span class="badge bg-warning text-dark ms-1">Yêu cầu cọc {{ money($a->deposit_required) }}</span>@endif
              </div>
            </div>
            <div class="col-12">
              <div class="small text-muted">Triệu chứng / lý do khám</div>
              <div>{{ $a->symptoms ?: '-' }}</div>
            </div>
            @if ($a->cancel_reason)
              <div class="col-12">
                <div class="alert alert-secondary mb-0 py-2">
                  <strong>Lý do hủy:</strong> {{ $a->cancel_reason }}
                  @if ($a->cancelledBy)<span class="small text-muted">({{ $a->cancelledBy->name }}, {{ $a->cancelled_at?->format('H:i d/m/Y') }})</span>@endif
                  @if ($a->is_late_cancel)<span class="badge bg-danger ms-1">Tính no-show</span>@endif
                </div>
              </div>
            @endif
            @if ($a->rescheduledFrom || $a->rescheduledTo)
              <div class="col-12 small">
                @if ($a->rescheduledFrom)<div><i class="bi bi-arrow-return-right me-1"></i>Đổi từ lịch <a href="{{ route('staff.appointments.show', $a->rescheduledFrom) }}">{{ $a->rescheduledFrom->code }}</a></div>@endif
                @if ($a->rescheduledTo)<div><i class="bi bi-arrow-right me-1"></i>Đã đổi sang lịch <a href="{{ route('staff.appointments.show', $a->rescheduledTo) }}">{{ $a->rescheduledTo->code }}</a></div>@endif
              </div>
            @endif
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Lịch sử trạng thái</div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Thời điểm</th><th>Chuyển trạng thái</th><th>Người thực hiện</th><th>Ghi chú</th></tr></thead>
            <tbody>
              @forelse ($a->statusLogs as $log)
                <tr>
                  <td class="text-nowrap small">{{ $log->created_at?->format('H:i:s d/m/Y') }}</td>
                  <td class="text-nowrap">
                    @if ($log->from_status){!! status_badge($log->from_status) !!} <i class="bi bi-arrow-right mx-1"></i>@endif
                    {!! status_badge($log->to_status) !!}
                  </td>
                  <td>{{ $log->user?->name ?? 'Hệ thống' }}</td>
                  <td class="small">{{ $log->note }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-muted">Chưa có lịch sử.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      @if ($a->payments->isNotEmpty())
        <div class="card">
          <div class="card-header">Thanh toán liên quan</div>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr><th>Mã giao dịch</th><th>Loại</th><th>Phương thức</th><th class="text-end">Số tiền</th><th>Trạng thái</th><th>Thời gian</th></tr></thead>
              <tbody>
                @foreach ($a->payments as $p)
                  <tr>
                    <td class="small">{{ $p->code }}</td>
                    <td>{{ $p->typeLabel() }}</td>
                    <td>{{ $p->method?->label() }}</td>
                    <td class="text-end">{{ money($p->amount) }}</td>
                    <td>{!! status_badge($p->status) !!}</td>
                    <td class="small">{{ ($p->paid_at ?? $p->created_at)->format('H:i d/m/Y') }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>

    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Bệnh nhân</span>
          @can('patients.view')<a href="{{ route('staff.patients.show', $a->patient) }}" class="small">Hồ sơ</a>@endcan
        </div>
        <div class="card-body">
          <h6 class="mb-1">{{ $a->patient->full_name }}</h6>
          <div class="small text-muted mb-2">{{ $a->patient->code }} · {{ $a->patient->summary() }}</div>
          <div class="small"><i class="bi bi-telephone me-1"></i>{{ $a->patient->phone ?: '-' }}</div>
          @if ($a->patient->address)<div class="small"><i class="bi bi-geo-alt me-1"></i>{{ $a->patient->address }}</div>@endif
          @if ($a->patient->user)
            <hr>
            <div class="small text-muted">Tài khoản quản lý</div>
            <div class="small">{{ $a->patient->user->name }} · {{ $a->patient->user->phone }}</div>
            <div class="small">Số lần vắng mặt:
              <span class="badge {{ $a->patient->user->exceedsNoShowThreshold() ? 'bg-danger' : 'bg-secondary' }}">{{ $a->patient->user->no_show_count }}/{{ setting('no_show_threshold') }}</span>
            </div>
          @endif
        </div>
      </div>

      <div class="card text-center">
        <div class="card-header">Mã QR check-in</div>
        <div class="card-body">
          <div class="d-inline-block p-2 border rounded" style="width:180px">{!! \App\Support\QrCode::svg($a->code, 160) !!}</div>
          <div class="fw-bold mt-2 fs-5 font-monospace">{{ $a->code }}</div>
          @if ($a->visit)
            <div class="small text-muted mt-1">Lượt khám {{ $a->visit->code }} - STT {{ $a->visit->queue_number }} ({{ $a->visit->status->label() }})</div>
          @endif
        </div>
      </div>
    </div>
  </div>

  @include('admin.reception.appointments._cancel_modal')
@endsection
