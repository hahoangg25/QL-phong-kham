@extends('common.layouts.print')

@section('title', 'Biên lai '.$invoice->code)

@section('head_right')
  <div>Số: <strong>{{ $invoice->code }}</strong></div>
  <div>Ngày: {{ ($invoice->paid_at ?? $invoice->created_at)->format('d/m/Y') }}</div>
@endsection

@section('content')
  <div class="doc-title">Biên lai thu tiền</div>
  <div class="text-center mb-3"><em>Hóa đơn dịch vụ khám chữa bệnh</em></div>

  <table class="w-100 mb-3">
    <tr>
      <td style="width:55%">Họ tên bệnh nhân: <strong>{{ $invoice->patient?->full_name }}</strong></td>
      <td>Mã BN: {{ $invoice->patient?->code }}</td>
    </tr>
    <tr>
      <td>Ngày sinh: {{ $invoice->patient?->date_of_birth?->format('d/m/Y') ?? '-' }} &nbsp; Giới tính: {{ $invoice->patient?->genderLabel() ?: '-' }}</td>
      <td>Điện thoại: {{ $invoice->patient?->phone ?? '-' }}</td>
    </tr>
    <tr>
      <td colspan="2">Địa chỉ: {{ $invoice->patient?->address ?? '-' }}</td>
    </tr>
    @if ($invoice->visit)
      <tr>
        <td>Bác sĩ: {{ $invoice->visit->doctor?->display_name }}</td>
        <td>Chuyên khoa: {{ $invoice->visit->specialty?->name }} · Lượt khám: {{ $invoice->visit->code }}</td>
      </tr>
    @endif
  </table>

  <table class="print-table">
    <thead>
      <tr>
        <th style="width:40px">STT</th>
        <th>Nội dung</th>
        <th style="width:60px">SL</th>
        <th style="width:110px">Đơn giá</th>
        <th style="width:120px">Thành tiền</th>
      </tr>
    </thead>
    <tbody>
      @php $i = 0; @endphp
      @foreach ($types as $type => $label)
        @if ($groups->has($type))
          <tr>
            <td colspan="4"><strong>{{ $label }}</strong></td>
            <td class="text-end"><strong>{{ number_format($groups[$type]->sum('amount'), 0, ',', '.') }}</strong></td>
          </tr>
          @foreach ($groups[$type] as $item)
            <tr>
              <td class="text-center">{{ ++$i }}</td>
              <td>{{ $item->description }}</td>
              <td class="text-center">{{ $item->quantity }}</td>
              <td class="text-end">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
              <td class="text-end">{{ number_format($item->amount, 0, ',', '.') }}</td>
            </tr>
          @endforeach
        @endif
      @endforeach
      <tr>
        <td colspan="4" class="text-end">Tổng cộng</td>
        <td class="text-end">{{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
      </tr>
      @if ($invoice->discount)
        <tr>
          <td colspan="4" class="text-end">Giảm giá</td>
          <td class="text-end">-{{ number_format($invoice->discount, 0, ',', '.') }}</td>
        </tr>
      @endif
      @if ($invoice->deposit_deducted)
        <tr>
          <td colspan="4" class="text-end">Đã thanh toán trước / đặt cọc</td>
          <td class="text-end">-{{ number_format($invoice->deposit_deducted, 0, ',', '.') }}</td>
        </tr>
      @endif
      <tr>
        <td colspan="4" class="text-end"><strong>Số tiền phải thanh toán</strong></td>
        <td class="text-end"><strong>{{ number_format($invoice->total, 0, ',', '.') }}</strong></td>
      </tr>
    </tbody>
  </table>

  <p class="mt-2 mb-1"><em>Số tiền bằng chữ: {{ $amountInWords }}.</em></p>
  <p class="mb-1">
    Trạng thái: <strong>{{ $invoice->status->label() }}</strong>
    @if ($invoice->paid_at)
      · Hình thức: {{ $invoice->payment_method?->label() }} · Thời gian: {{ $invoice->paid_at->format('H:i d/m/Y') }}
    @endif
  </p>
  @if ($invoice->cancel_reason)
    <p class="mb-1">Lý do hủy/hoàn: {{ $invoice->cancel_reason }}</p>
  @endif

  <div class="row mt-4">
    <div class="col-6 sign-box">
      <strong>Người nộp tiền</strong><br><em>(Ký, ghi rõ họ tên)</em>
      <div style="margin-top:70px">{{ $invoice->patient?->full_name }}</div>
    </div>
    <div class="col-6 sign-box">
      <em>Ngày {{ ($invoice->paid_at ?? now())->format('d') }} tháng {{ ($invoice->paid_at ?? now())->format('m') }} năm {{ ($invoice->paid_at ?? now())->format('Y') }}</em><br>
      <strong>Thu ngân</strong><br><em>(Ký, ghi rõ họ tên)</em>
      <div style="margin-top:50px">{{ $invoice->cashier?->name ?? auth()->user()->name }}</div>
    </div>
  </div>
@endsection
