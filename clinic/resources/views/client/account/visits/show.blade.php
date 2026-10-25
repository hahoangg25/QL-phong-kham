@extends('client.layouts.account')

@section('title', 'Kết quả khám '.$visit->code)
@section('page_title', 'Kết quả khám bệnh')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
  <li><a href="{{ route('account.visits.index') }}">Lịch sử khám</a></li>
@endsection

@section('account_title')
  Lượt khám {{ $visit->code }} {!! status_badge($visit->status) !!}
@endsection
@section('account_actions')
  <a href="{{ route('account.visits.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Danh sách</a>
  <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>In</button>
@endsection

@section('account_content')
  @php $v = $visit; @endphp

  <div class="card card-soft mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="small text-muted">Bệnh nhân</div>
          <div class="fw-semibold">{{ $v->patient->full_name }}</div>
          <div class="small text-muted">{{ $v->patient->summary() }} · Mã BN {{ $v->patient->code }}</div>
        </div>
        <div class="col-md-6">
          <div class="small text-muted">Bác sĩ khám</div>
          <div class="fw-semibold">{{ $v->doctor->display_name }}</div>
          <div class="small text-muted">{{ $v->specialty->name }} · {{ ($v->room ?? $v->doctor->room)?->label() }}</div>
        </div>
        <div class="col-md-6">
          <div class="small text-muted">Ngày khám</div>
          <div class="fw-semibold">{{ $v->visit_date->translatedFormat('l, d/m/Y') }}{{ $v->started_at ? ' · '.$v->started_at->format('H:i') : '' }}</div>
        </div>
        <div class="col-md-6">
          <div class="small text-muted">Lý do khám</div>
          <div>{{ $v->reason ?: ($v->symptoms ?: '-') }}</div>
        </div>
      </div>
    </div>
  </div>

  @if ($v->status->value !== 'completed')
    <div class="alert alert-info small"><i class="bi bi-hourglass-split me-1"></i>Lượt khám đang được thực hiện ({{ $v->status->label() }}). Kết quả đầy đủ sẽ hiển thị khi bác sĩ hoàn thành.</div>
  @endif

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card card-soft h-100">
        <div class="card-header"><i class="bi bi-heart-pulse me-1"></i>Khám lâm sàng</div>
        <div class="card-body small">
          <p class="mb-2"><strong>Sinh hiệu:</strong> {{ $v->vitalsSummary() ?: 'Chưa ghi nhận' }}@if ($v->bmi()) · BMI {{ $v->bmi() }}@endif</p>
          @if ($v->symptoms)<p class="mb-2"><strong>Triệu chứng:</strong> {{ $v->symptoms }}</p>@endif
          @if ($v->clinical_notes)<p class="mb-0"><strong>Khám:</strong> {{ $v->clinical_notes }}</p>@endif
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card card-soft h-100">
        <div class="card-header"><i class="bi bi-clipboard2-check me-1"></i>Chẩn đoán & kết luận</div>
        <div class="card-body small">
          @forelse ($v->diagnoses as $d)
            <div class="mb-1">
              <span class="badge {{ $d->is_primary ? 'bg-primary' : 'bg-secondary' }} me-1">{{ $d->icd10_code }}</span>
              {{ $d->name }} <span class="text-muted">({{ $d->is_primary ? 'bệnh chính' : 'bệnh kèm theo' }})</span>
            </div>
          @empty
            <div class="text-muted">Chưa có chẩn đoán.</div>
          @endforelse
          @if ($v->conclusion)<p class="mt-2 mb-1"><strong>Kết luận:</strong> {{ $v->conclusion }}</p>@endif
          @if ($v->advice)<p class="mb-1"><strong>Lời dặn:</strong> {{ $v->advice }}</p>@endif
          @if ($v->follow_up_date)<p class="mb-0 text-primary"><i class="bi bi-calendar-event me-1"></i><strong>Hẹn tái khám:</strong> {{ $v->follow_up_date->format('d/m/Y') }}</p>@endif
        </div>
      </div>
    </div>
  </div>

  @if ($v->serviceOrders->isNotEmpty())
    <div class="card card-soft mt-3">
      <div class="card-header"><i class="bi bi-droplet-half me-1"></i>Kết quả cận lâm sàng</div>
      <div class="card-body">
        @foreach ($v->serviceOrders as $order)
          <div class="border rounded-3 p-3 mb-2">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
              <strong>{{ $order->service->name }}</strong>
              {!! status_badge($order->status) !!}
            </div>
            @if ($order->result)
              <pre class="small mb-2 bg-light p-2 rounded" style="white-space: pre-wrap; font-family: inherit">{{ $order->result->result }}</pre>
              @if ($order->result->conclusion)<div class="small"><strong>Kết luận:</strong> {{ $order->result->conclusion }}</div>@endif
              <div class="small text-muted">Trả kết quả lúc {{ $order->result->completed_at->format('H:i d/m/Y') }}</div>
              @if ($order->result->attachment_path)
                <div class="mt-2">
                  @if ($order->result->isImageAttachment())
                    <div class="mb-2">
                      <a href="{{ route('account.lab-results.attachment', $order->result) }}" target="_blank">
                        <img src="{{ route('account.lab-results.attachment', $order->result) }}" alt="Ảnh phim X-Quang" class="img-thumbnail" style="max-height: 150px;">
                      </a>
                    </div>
                  @endif
                  <a href="{{ route('account.lab-results.attachment', $order->result) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>{{ $order->result->attachment_name ?: 'Xem phim X-Quang / Tài liệu' }}
                  </a>
                </div>
              @endif
            @else
              <div class="small text-muted">Chưa có kết quả.</div>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  @endif

  @if ($v->prescription)
    <div class="card card-soft mt-3">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-capsule me-1"></i>Đơn thuốc {{ $v->prescription->code }}</span>
        {!! status_badge($v->prescription->status) !!}
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
          <thead class="table-light"><tr><th>#</th><th>Tên thuốc</th><th class="text-center">SL</th><th>Liều dùng</th><th>Cách dùng</th><th class="text-center">Số ngày</th></tr></thead>
          <tbody>
            @foreach ($v->prescription->items as $i => $item)
              <tr>
                <td>{{ $i + 1 }}</td>
                <td><strong>{{ $item->medicine->fullName() }}</strong><div class="text-muted">{{ $item->medicine->active_ingredient }}</div></td>
                <td class="text-center">{{ $item->quantity }} {{ $item->medicine->unit }}</td>
                <td>{{ $item->dosage }}</td>
                <td>{{ $item->usage }}</td>
                <td class="text-center">{{ $item->days }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if ($v->prescription->note)<div class="card-footer bg-transparent small"><strong>Ghi chú:</strong> {{ $v->prescription->note }}</div>@endif
    </div>
  @endif

  @if ($v->addenda->isNotEmpty())
    <div class="card card-soft mt-3">
      <div class="card-header"><i class="bi bi-pencil-square me-1"></i>Ghi chú bổ sung của bác sĩ</div>
      <div class="card-body small">
        @foreach ($v->addenda as $add)
          <div class="mb-2"><div>{{ $add->content }}</div><div class="text-muted">{{ $add->user->name }} · {{ $add->created_at->format('H:i d/m/Y') }}</div></div>
        @endforeach
      </div>
    </div>
  @endif

  <div class="row g-3 mt-0">
    @if ($v->invoice)
      <div class="col-md-6">
        <div class="card card-soft h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="icon-circle"><i class="bi bi-receipt"></i></div>
            <div class="flex-grow-1">
              <div class="fw-semibold">Hóa đơn {{ $v->invoice->code }}</div>
              <div class="small">{{ money($v->invoice->total) }} · {!! status_badge($v->invoice->status) !!}</div>
            </div>
            <a href="{{ route('account.invoices.show', $v->invoice) }}" class="btn btn-sm btn-outline-primary">Xem</a>
          </div>
        </div>
      </div>
    @endif

    <div class="col-md-6">
      <div class="card card-soft h-100">
        <div class="card-header"><i class="bi bi-star me-1"></i>Đánh giá bác sĩ</div>
        <div class="card-body">
          @if ($v->review)
            <div class="rating-stars mb-1">
              @for ($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= $v->review->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor
            </div>
            <div class="small">{{ $v->review->comment ?: 'Không có nhận xét.' }}</div>
            <div class="small text-muted">{{ $v->review->created_at->format('d/m/Y') }}</div>
          @elseif ($canReview)
            <form method="POST" action="{{ route('account.visits.review', $v) }}">
              @csrf
              <div class="rating-input mb-2" id="ratingInput">
                @for ($i = 1; $i <= 5; $i++)
                  <input type="radio" class="btn-check" name="rating" id="rate{{ $i }}" value="{{ $i }}" @checked(old('rating', 5) == $i)>
                  <label class="btn btn-sm btn-outline-warning" for="rate{{ $i }}">{{ $i }} <i class="bi bi-star-fill"></i></label>
                @endfor
              </div>
              <textarea name="comment" rows="2" maxlength="1000" class="form-control form-control-sm mb-2" placeholder="Chia sẻ trải nghiệm của bạn (không bắt buộc)">{{ old('comment') }}</textarea>
              <button class="btn btn-primary btn-sm">Gửi đánh giá</button>
            </form>
          @else
            <div class="small text-muted">Bạn có thể đánh giá sau khi lượt khám hoàn thành.</div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
