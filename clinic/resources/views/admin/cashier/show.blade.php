@extends('admin.layouts.app')

@section('title', 'Hóa đơn '.$invoice->code)
@section('page_title', 'Hóa đơn '.$invoice->code)
@section('breadcrumbs')
  <a href="{{ route('staff.cashier.index') }}">Thu ngân</a> / {{ $invoice->code }}
@endsection

@section('content')
  @if (session('print_url'))
    <div class="alert alert-success d-flex align-items-center gap-2">
      <i class="bi bi-printer fs-5"></i>
      <div>Thanh toán hoàn tất. <a href="{{ session('print_url') }}" target="_blank" class="fw-semibold">In biên lai</a></div>
    </div>
  @endif

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center">
          <span>Thông tin hóa đơn</span>
          <span class="ms-auto">{!! status_badge($invoice->status) !!}</span>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="text-muted small">Bệnh nhân</div>
              <div class="fw-semibold">{{ $invoice->patient?->full_name }}</div>
              <div class="small text-muted">
                {{ $invoice->patient?->code }}
                @if ($invoice->patient?->date_of_birth) · {{ $invoice->patient->date_of_birth->format('d/m/Y') }} ({{ $invoice->patient->age }} tuổi)@endif
                @if ($invoice->patient?->phone) · {{ $invoice->patient->phone }}@endif
              </div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Lượt khám</div>
              @if ($invoice->visit)
                <div class="fw-semibold">{{ $invoice->visit->code }} · {{ $invoice->visit->visit_date->format('d/m/Y') }}</div>
                <div class="small text-muted">{{ $invoice->visit->doctor?->display_name }} · {{ $invoice->visit->specialty?->name }}</div>
                @if ($invoice->visit->diagnoses->isNotEmpty())
                  <div class="small mt-1">Chẩn đoán: {{ $invoice->visit->diagnoses->map->label()->implode('; ') }}</div>
                @endif
              @else
                <div class="text-muted">-</div>
              @endif
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Ngày lập</div>
              <div>{{ $invoice->created_at->format('H:i d/m/Y') }} @if ($invoice->creator)<span class="text-muted small">bởi {{ $invoice->creator->name }}</span>@endif</div>
            </div>
            @if ($invoice->paid_at)
              <div class="col-md-6">
                <div class="text-muted small">Thanh toán</div>
                <div>{{ $invoice->paid_at->format('H:i d/m/Y') }} · {{ $invoice->payment_method?->label() }}
                  @if ($invoice->cashier)<span class="text-muted small">({{ $invoice->cashier->name }})</span>@endif</div>
              </div>
            @endif
            @if ($invoice->cancel_reason)
              <div class="col-12">
                <div class="alert alert-secondary mb-0 py-2">
                  <strong>{{ $invoice->status->label() }}</strong>
                  @if ($invoice->cancelled_at) lúc {{ $invoice->cancelled_at->format('H:i d/m/Y') }}@endif
                  @if ($invoice->canceller) bởi {{ $invoice->canceller->name }}@endif
                  — Lý do: {{ $invoice->cancel_reason }}
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">Chi tiết dịch vụ</div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Nội dung</th>
                <th class="text-center">SL</th>
                <th class="text-end">Đơn giá</th>
                <th class="text-end">Thành tiền</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($types as $type => $label)
                @if ($groups->has($type))
                  <tr class="table-light">
                    <td colspan="3" class="fw-semibold">{{ $label }}</td>
                    <td class="text-end fw-semibold">{{ money($groups[$type]->sum('amount')) }}</td>
                  </tr>
                  @foreach ($groups[$type] as $item)
                    <tr>
                      <td class="ps-4">{{ $item->description }}</td>
                      <td class="text-center">{{ $item->quantity }}</td>
                      <td class="text-end">{{ money($item->unit_price) }}</td>
                      <td class="text-end">{{ money($item->amount) }}</td>
                    </tr>
                  @endforeach
                @endif
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="text-end">Tổng cộng</td>
                <td class="text-end fw-semibold">{{ money($invoice->subtotal) }}</td>
              </tr>
              @if ($invoice->discount)
                <tr>
                  <td colspan="3" class="text-end">Giảm giá</td>
                  <td class="text-end">- {{ money($invoice->discount) }}</td>
                </tr>
              @endif
              @if ($invoice->deposit_deducted)
                <tr>
                  <td colspan="3" class="text-end">Trừ tiền đã thanh toán trước / đặt cọc</td>
                  <td class="text-end text-success">- {{ money($invoice->deposit_deducted) }}</td>
                </tr>
              @endif
              <tr>
                <td colspan="3" class="text-end fw-bold">Phải thanh toán</td>
                <td class="text-end fw-bold fs-5 text-primary">{{ money($invoice->total) }}</td>
              </tr>
              <tr>
                <td colspan="3" class="text-end">Đã thanh toán</td>
                <td class="text-end">{{ money($invoice->paid_amount) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">Lịch sử giao dịch</div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Mã giao dịch</th>
                <th>Thời gian</th>
                <th>Loại</th>
                <th>Phương thức</th>
                <th class="text-end">Số tiền</th>
                <th>Trạng thái</th>
                <th>Người thực hiện</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($invoice->payments->sortByDesc('id') as $payment)
                <tr>
                  <td>
                    {{ $payment->code }}
                    @if ($payment->transaction_no)<div class="small text-muted">Mã GD: {{ $payment->transaction_no }}</div>@endif
                  </td>
                  <td>{{ ($payment->paid_at ?? $payment->created_at)->format('H:i d/m/Y') }}</td>
                  <td>{{ $payment->typeLabel() }}</td>
                  <td>{{ $payment->method?->label() }}</td>
                  <td class="text-end {{ $payment->type === 'refund' ? 'text-danger' : '' }}">{{ $payment->type === 'refund' ? '-' : '' }}{{ money($payment->amount) }}</td>
                  <td>{!! status_badge($payment->status) !!}</td>
                  <td class="small">{{ $payment->receiver?->name ?? 'Online' }}@if ($payment->note)<div class="text-muted">{{ $payment->note }}</div>@endif</td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-3">Chưa có giao dịch.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      @if ($invoice->status->value === 'unpaid')
        <div class="card mb-3 border-primary">
          <div class="card-header"><i class="bi bi-cash-coin me-1"></i>Thu tiền</div>
          <div class="card-body">
            <div class="text-center mb-3">
              <div class="text-muted small">Số tiền cần thu</div>
              <div class="display-6 fw-bold text-primary">{{ money($invoice->amountDue()) }}</div>
            </div>
            <form method="POST" action="{{ route('staff.cashier.pay', $invoice) }}" data-confirm="Xác nhận đã thu {{ money($invoice->amountDue()) }}?">
              @csrf
              <div class="mb-3">
                <label class="form-label required">Phương thức</label>
                @foreach ($methods as $value => $label)
                  <div class="form-check">
                    <input class="form-check-input pay-method" type="radio" name="method" id="method_{{ $value }}" value="{{ $value }}" @checked(old('method', 'cash') === $value)>
                    <label class="form-check-label" for="method_{{ $value }}">{{ $label }}</label>
                  </div>
                @endforeach
              </div>
              <div class="mb-3" id="transactionField">
                <label class="form-label">Mã giao dịch chuyển khoản</label>
                <input type="text" name="transaction_no" value="{{ old('transaction_no') }}" class="form-control @error('transaction_no') is-invalid @enderror" placeholder="VD: FT2610123456">
              </div>
              <div class="mb-3">
                <label class="form-label">Ghi chú</label>
                <input type="text" name="note" value="{{ old('note') }}" class="form-control" maxlength="500">
              </div>
              <button class="btn btn-primary w-100"><i class="bi bi-check2-circle me-1"></i>Xác nhận thu tiền</button>
            </form>
          </div>
        </div>
        <div class="card">
          <div class="card-body">
            <form method="POST" action="{{ route('staff.cashier.cancel', $invoice) }}" data-prompt="Nhập lý do hủy hóa đơn {{ $invoice->code }}:" data-prompt-field="reason">
              @csrf
              <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-1"></i>Hủy hóa đơn</button>
            </form>
          </div>
        </div>
      @else
        <div class="card mb-3">
          <div class="card-body d-grid gap-2">
            <a href="{{ route('staff.cashier.print', $invoice) }}" target="_blank" class="btn btn-primary"><i class="bi bi-printer me-1"></i>In biên lai / xuất PDF</a>
            @if ($invoice->status->value === 'paid')
              <form method="POST" action="{{ route('staff.cashier.refund', $invoice) }}" data-prompt="Nhập lý do hoàn tiền hóa đơn {{ $invoice->code }} ({{ money($invoice->paid_amount) }}):" data-prompt-field="reason" class="d-grid">
                @csrf
                <button class="btn btn-outline-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Hoàn tiền</button>
              </form>
            @endif
          </div>
        </div>
      @endif
      <a href="{{ route('staff.cashier.index') }}" class="btn btn-link px-0 mt-2"><i class="bi bi-arrow-left me-1"></i>Quay lại danh sách</a>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      const field = document.getElementById('transactionField');
      if (!field) return;
      const sync = () => {
        const checked = document.querySelector('.pay-method:checked');
        field.style.display = checked && checked.value === 'transfer' ? '' : 'none';
      };
      document.querySelectorAll('.pay-method').forEach((r) => r.addEventListener('change', sync));
      sync();
    })();
  </script>
@endpush
