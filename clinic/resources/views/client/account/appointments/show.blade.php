@extends('client.layouts.account')

@section('title', 'Lịch hẹn '.$appointment->code)
@section('page_title', 'Chi tiết lịch hẹn')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
  <li><a href="{{ route('account.appointments.index') }}">Lịch hẹn</a></li>
@endsection

@section('account_title')
  Lịch hẹn <span class="text-primary">{{ $appointment->code }}</span> {!! status_badge($appointment->status) !!}
@endsection
@section('account_actions')
  <a href="{{ route('account.appointments.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Danh sách</a>
@endsection

@section('account_content')
  @php
    $a = $appointment;
    $room = $a->visit?->room ?? $a->timeSlot?->room ?? $a->doctor->room;
    $active = in_array($a->status, \App\Enums\AppointmentStatus::active(), true);
  @endphp

  @if ($showQueue)
    <div class="card card-soft mb-3 border-start border-4 border-primary" id="queueBox" data-url="{{ route('account.appointments.queue', $a) }}">
      <div class="card-body d-flex flex-wrap align-items-center gap-4">
        <div class="text-center">
          <div class="small text-muted">Số thứ tự của bạn</div>
          <div class="queue-number-big" id="qNumber">{{ $a->visit->queue_number }}</div>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold mb-1"><i class="bi bi-broadcast me-1 text-danger"></i>Theo dõi hàng đợi hôm nay</div>
          <div>Trạng thái: <strong id="qStatus">{{ $a->visit->status->label() }}</strong></div>
          <div>Số người còn trước bạn: <strong id="qAhead" class="text-primary fs-5">–</strong></div>
          <div class="small text-muted">Phòng: <span id="qRoom">{{ $room?->label() ?? '-' }}</span> · tự cập nhật mỗi 15 giây</div>
        </div>
      </div>
    </div>
  @endif

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card card-soft mb-3">
        <div class="card-header"><i class="bi bi-info-circle me-1"></i>Thông tin lịch hẹn</div>
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-3">
            <img src="{{ $a->doctor->avatarUrl() }}" class="rounded-circle" width="60" height="60" style="object-fit:cover" alt="">
            <div>
              <div class="fw-semibold fs-5">{{ $a->doctor->display_name }}</div>
              <div class="text-muted small">{{ $a->specialty->name }} · Phí khám {{ money($a->doctor->consultation_fee) }}</div>
            </div>
          </div>
          <dl class="row mb-0">
            <dt class="col-sm-4 text-muted fw-normal">Người khám</dt>
            <dd class="col-sm-8 fw-semibold">{{ $a->patient->full_name }} <span class="text-muted fw-normal small">({{ $a->patient->relationship }}, mã {{ $a->patient->code }})</span></dd>
            <dt class="col-sm-4 text-muted fw-normal">Thời gian</dt>
            <dd class="col-sm-8 fw-semibold">{{ $a->timeLabel() }}</dd>
            <dt class="col-sm-4 text-muted fw-normal">Phòng khám</dt>
            <dd class="col-sm-8">{{ $room?->label() ?? '-' }}</dd>
            <dt class="col-sm-4 text-muted fw-normal">Hình thức</dt>
            <dd class="col-sm-8">{{ \App\Models\Scheduling\Appointment::SOURCES[$a->source] ?? $a->source }}</dd>
            @if ($a->symptoms)
              <dt class="col-sm-4 text-muted fw-normal">Triệu chứng</dt>
              <dd class="col-sm-8">{{ $a->symptoms }}</dd>
            @endif
            @if ($a->deposit_required > 0 || $a->prepaid_amount > 0)
              <dt class="col-sm-4 text-muted fw-normal">Thanh toán trước</dt>
              <dd class="col-sm-8">
                Đã trả <strong class="text-success">{{ money($a->prepaid_amount) }}</strong>
                @if ($a->deposit_required > 0) / cọc yêu cầu {{ money($a->deposit_required) }}@endif
              </dd>
            @endif
            @if ($a->cancel_reason)
              <dt class="col-sm-4 text-muted fw-normal">Lý do hủy</dt>
              <dd class="col-sm-8 text-danger">{{ $a->cancel_reason }}@if ($a->is_late_cancel) <span class="badge bg-danger-subtle text-danger">Tính 1 lần vắng mặt</span>@endif</dd>
            @endif
            @if ($a->rescheduledFrom)
              <dt class="col-sm-4 text-muted fw-normal">Đổi từ lịch</dt>
              <dd class="col-sm-8"><a href="{{ route('account.appointments.show', $a->rescheduledFrom) }}">{{ $a->rescheduledFrom->code }}</a></dd>
            @endif
            @if ($a->rescheduledTo)
              <dt class="col-sm-4 text-muted fw-normal">Lịch mới</dt>
              <dd class="col-sm-8"><a href="{{ route('account.appointments.show', $a->rescheduledTo) }}">{{ $a->rescheduledTo->code }}</a></dd>
            @endif
          </dl>
        </div>
        @if ($a->status->isCancellable() || $canPay || $a->visit)
          <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
            @if ($canPay)
              <form method="POST" action="{{ route('account.appointments.pay', $a) }}" class="d-flex flex-wrap gap-2">
                @csrf
                <button name="gateway" value="vnpay" class="btn btn-success btn-sm"><i class="bi bi-credit-card me-1"></i>Thanh toán VNPay {{ money($a->payableOnlineAmount()) }}</button>
                <button name="gateway" value="momo" class="btn btn-sm text-white" style="background:#a50064"><i class="bi bi-wallet2 me-1"></i>Thanh toán ví MoMo</button>
              </form>
            @endif
            @if ($a->status->isCancellable())
              <a href="{{ route('account.appointments.reschedule', $a) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Đổi lịch</a>
              <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal"><i class="bi bi-x-circle me-1"></i>Hủy lịch</button>
            @endif
            @if ($a->visit && $a->visit->status->value === 'completed')
              <a href="{{ route('account.visits.show', $a->visit) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-medical me-1"></i>Xem kết quả khám</a>
            @endif
          </div>
        @endif
      </div>

      @if ($a->payments->isNotEmpty())
        <div class="card card-soft mb-3">
          <div class="card-header"><i class="bi bi-wallet2 me-1"></i>Giao dịch thanh toán</div>
          <div class="table-responsive">
            <table class="table table-sm mb-0 small align-middle">
              <thead><tr><th>Mã GD</th><th>Thời gian</th><th class="text-end">Số tiền</th><th>Trạng thái</th></tr></thead>
              <tbody>
                @foreach ($a->payments->sortByDesc('id') as $p)
                  <tr>
                    <td>{{ $p->code }}</td>
                    <td>{{ ($p->paid_at ?? $p->created_at)->format('H:i d/m/Y') }}</td>
                    <td class="text-end">{{ money($p->amount) }}</td>
                    <td>{!! status_badge($p->status) !!}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>

    <div class="col-lg-5">
      @if ($active)
        <div class="card card-soft mb-3 text-center">
          <div class="card-body">
            <div class="fw-semibold mb-2">Mã check-in tại quầy lễ tân</div>
            <div class="qr-box mb-2">{!! $qr !!}</div>
            <div class="fs-4 fw-bold text-primary letter-spacing">{{ $a->code }}</div>
            <div class="small text-muted">Vui lòng đến trước giờ hẹn 15 phút. Đến trễ quá {{ setting('late_checkin_minutes') }} phút sẽ được xếp cuối hàng đợi.</div>
          </div>
        </div>
      @endif

      <div class="card card-soft">
        <div class="card-header"><i class="bi bi-clock-history me-1"></i>Lịch sử trạng thái</div>
        <div class="card-body">
          <ul class="timeline-status">
            @foreach ($a->statusLogs as $log)
              <li>
                <div class="fw-semibold">{{ $log->to_status->label() }}</div>
                <div class="text-muted small">{{ $log->created_at->format('H:i d/m/Y') }} · {{ $log->user?->name ?? 'Hệ thống' }}</div>
                @if ($log->note)<div class="small">{{ $log->note }}</div>@endif
              </li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  </div>

  @if ($a->status->isCancellable())
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" action="{{ route('account.appointments.cancel', $a) }}" class="modal-content">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Hủy lịch hẹn {{ $a->code }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
          </div>
          <div class="modal-body">
            @if ($lateCancel)
              <div class="alert alert-warning small">
                <i class="bi bi-exclamation-triangle me-1"></i>Còn dưới <strong>{{ $cancelHours }} giờ</strong> đến giờ khám.
                Hủy lúc này sẽ được tính <strong>1 lần vắng mặt</strong> (BR-04). Vắng mặt nhiều lần có thể bị hạn chế đặt lịch online.
              </div>
            @else
              <p class="small text-muted">Hủy miễn phí vì còn hơn {{ $cancelHours }} giờ trước giờ khám. Khung giờ sẽ được giải phóng cho người khác.</p>
            @endif
            <label class="form-label">Lý do hủy</label>
            <textarea name="reason" rows="3" maxlength="500" class="form-control" placeholder="vd: Bận việc đột xuất"></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
            <button class="btn btn-danger">Xác nhận hủy lịch</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endsection

@if ($showQueue)
  @push('scripts')
    <script>
      (function () {
        const box = document.getElementById('queueBox');
        App.poll(async () => {
          try {
            const d = await App.fetchJson(box.dataset.url);
            document.getElementById('qStatus').textContent = d.status_label;
            document.getElementById('qAhead').textContent = d.status === 'waiting' ? d.people_ahead : '0';
            if (d.queue_number) document.getElementById('qNumber').textContent = d.queue_number;
            if (d.room) document.getElementById('qRoom').textContent = d.room;
          } catch (e) { /* bỏ qua lỗi mạng tạm thời */ }
        }, 15000);
      })();
    </script>
  @endpush
@endif
