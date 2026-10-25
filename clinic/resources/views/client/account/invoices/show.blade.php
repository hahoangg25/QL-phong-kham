@extends('client.layouts.account')

@section('title', 'Hóa đơn '.$invoice->code)
@section('page_title', 'Chi tiết hóa đơn')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
  <li><a href="{{ route('account.invoices.index') }}">Hóa đơn</a></li>
@endsection

@section('account_title')
  Hóa đơn {{ $invoice->code }} {!! status_badge($invoice->status) !!}
@endsection
@section('account_actions')
  <a href="{{ route('account.invoices.pdf', $invoice) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Tải PDF</a>
@endsection

@section('account_content')
  <div class="card card-soft">
    <div class="card-body">
      <div class="row g-3 mb-3 small">
        <div class="col-md-6">
          <div class="text-muted">Bệnh nhân</div>
          <div class="fw-semibold">{{ $invoice->patient->full_name }} (mã {{ $invoice->patient->code }})</div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Lượt khám</div>
          <div class="fw-semibold">
            @if ($invoice->visit)
              <a href="{{ route('account.visits.show', $invoice->visit) }}">{{ $invoice->visit->code }}</a>
              · {{ $invoice->visit->specialty?->name }} · {{ $invoice->visit->doctor?->display_name }}
            @else - @endif
          </div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Ngày lập</div>
          <div>{{ $invoice->created_at->format('H:i d/m/Y') }}</div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Thanh toán</div>
          <div>
            @if ($invoice->paid_at)
              {{ $invoice->paid_at->format('H:i d/m/Y') }} · {{ $invoice->payment_method?->label() ?? '-' }}
              @if ($invoice->cashier) · Thu ngân {{ $invoice->cashier->name }}@endif
            @else
              Chưa thanh toán
            @endif
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle small">
          <thead class="table-light"><tr><th>Nội dung</th><th>Loại</th><th class="text-center">SL</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th></tr></thead>
          <tbody>
            @foreach ($invoice->items as $item)
              <tr>
                <td>{{ $item->description }}</td>
                <td class="text-muted">{{ $item->typeLabel() }}</td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-end">{{ money($item->unit_price) }}</td>
                <td class="text-end">{{ money($item->amount) }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr><td colspan="4" class="text-end">Tạm tính</td><td class="text-end">{{ money($invoice->subtotal) }}</td></tr>
            @if ($invoice->discount)<tr><td colspan="4" class="text-end">Giảm giá</td><td class="text-end">- {{ money($invoice->discount) }}</td></tr>@endif
            @if ($invoice->deposit_deducted)<tr><td colspan="4" class="text-end">Đã thanh toán trước (đặt cọc online)</td><td class="text-end">- {{ money($invoice->deposit_deducted) }}</td></tr>@endif
            <tr class="fs-6"><th colspan="4" class="text-end">Tổng phải trả</th><th class="text-end text-primary">{{ money($invoice->total) }}</th></tr>
            @if ($invoice->paid_amount)<tr><td colspan="4" class="text-end">Đã thanh toán</td><td class="text-end text-success">{{ money($invoice->paid_amount) }}</td></tr>@endif
          </tfoot>
        </table>
      </div>

      @if ($invoice->cancel_reason)
        <div class="alert alert-secondary small mb-0">Lý do {{ $invoice->status->value === 'refunded' ? 'hoàn tiền' : 'hủy' }}: {{ $invoice->cancel_reason }}</div>
      @endif

      @if ($invoice->status->value === 'unpaid' && $invoice->amountDue() > 0)
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 rounded-3" style="background:#f4f8ff">
          <div class="small">Còn phải trả <strong class="text-danger fs-6">{{ money($invoice->amountDue()) }}</strong>. Bạn có thể thanh toán tại quầy thu ngân hoặc online.</div>
          <form method="POST" action="{{ route('account.invoices.pay', $invoice) }}" class="d-flex flex-wrap gap-2">
            @csrf
            <button name="gateway" value="vnpay" class="btn btn-success btn-sm"><i class="bi bi-credit-card me-1"></i>Thanh toán VNPay</button>
            <button name="gateway" value="momo" class="btn btn-sm text-white" style="background:#a50064"><i class="bi bi-wallet2 me-1"></i>Thanh toán ví MoMo</button>
          </form>
        </div>
      @endif
    </div>
  </div>

  @if ($invoice->payments->isNotEmpty())
    <div class="card card-soft mt-3">
      <div class="card-header"><i class="bi bi-list-check me-1"></i>Giao dịch</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small align-middle">
          <thead class="table-light"><tr><th>Mã GD</th><th>Loại</th><th>Phương thức</th><th>Thời gian</th><th class="text-end">Số tiền</th><th>Trạng thái</th></tr></thead>
          <tbody>
            @foreach ($invoice->payments->sortByDesc('id') as $p)
              <tr>
                <td>{{ $p->code }}</td>
                <td>{{ $p->typeLabel() }}</td>
                <td>{{ $p->method?->label() }}</td>
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
@endsection
