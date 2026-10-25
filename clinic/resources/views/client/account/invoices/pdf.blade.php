<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Hóa đơn {{ $invoice->code }}</title>
  <style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 10.5px; color: #111; margin: 0; }
    .head { border-bottom: 1.5px solid #000; padding-bottom: 6px; margin-bottom: 8px; }
    .head .name { font-size: 13px; font-weight: bold; text-transform: uppercase; }
    h1 { text-align: center; font-size: 16px; margin: 8px 0 2px; text-transform: uppercase; }
    .center { text-align: center; }
    .muted { color: #555; }
    table { width: 100%; border-collapse: collapse; }
    table.items th, table.items td { border: 0.6px solid #444; padding: 3px 4px; }
    table.items th { background: #eee; }
    .right { text-align: right; }
    .info td { padding: 1px 0; vertical-align: top; }
    .total td { padding: 2px 4px; }
    .sign { margin-top: 18px; }
    .sign td { text-align: center; width: 50%; height: 70px; vertical-align: top; }
  </style>
</head>
<body>
  <div class="head">
    <div class="name">{{ config('clinic.name') }}</div>
    <div>{{ config('clinic.address') }}</div>
    <div>Hotline: {{ config('clinic.hotline') }} - {{ config('clinic.email') }}</div>
  </div>

  <h1>Hóa đơn thanh toán</h1>
  <div class="center muted">Số: {{ $invoice->code }} - Ngày {{ $invoice->created_at->format('d/m/Y') }}</div>

  <table class="info" style="margin: 8px 0">
    <tr><td style="width:28%">Bệnh nhân:</td><td><strong>{{ $invoice->patient->full_name }}</strong> (mã {{ $invoice->patient->code }})</td></tr>
    <tr><td>Ngày sinh / Giới tính:</td><td>{{ $invoice->patient->date_of_birth?->format('d/m/Y') ?? '-' }} / {{ $invoice->patient->genderLabel() ?: '-' }}</td></tr>
    @if ($invoice->visit)
      <tr><td>Lượt khám:</td><td>{{ $invoice->visit->code }} - {{ $invoice->visit->specialty?->name }} - {{ $invoice->visit->doctor?->display_name }}</td></tr>
    @endif
    <tr><td>Trạng thái:</td><td>{{ $invoice->status->label() }}@if ($invoice->paid_at) - {{ $invoice->payment_method?->label() }} lúc {{ $invoice->paid_at->format('H:i d/m/Y') }}@endif</td></tr>
  </table>

  <table class="items">
    <thead><tr><th style="width:5%">#</th><th>Nội dung</th><th style="width:8%">SL</th><th style="width:18%">Đơn giá</th><th style="width:20%">Thành tiền</th></tr></thead>
    <tbody>
      @foreach ($invoice->items as $i => $item)
        <tr>
          <td class="center">{{ $i + 1 }}</td>
          <td>{{ $item->description }}</td>
          <td class="center">{{ $item->quantity }}</td>
          <td class="right">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
          <td class="right">{{ number_format($item->amount, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="total" style="margin-top:4px">
    <tr><td class="right">Tạm tính:</td><td class="right" style="width:20%">{{ number_format($invoice->subtotal, 0, ',', '.') }} đ</td></tr>
    @if ($invoice->discount)<tr><td class="right">Giảm giá:</td><td class="right">- {{ number_format($invoice->discount, 0, ',', '.') }} đ</td></tr>@endif
    @if ($invoice->deposit_deducted)<tr><td class="right">Đã thanh toán trước:</td><td class="right">- {{ number_format($invoice->deposit_deducted, 0, ',', '.') }} đ</td></tr>@endif
    <tr><td class="right"><strong>Tổng phải trả:</strong></td><td class="right"><strong>{{ number_format($invoice->total, 0, ',', '.') }} đ</strong></td></tr>
    <tr><td class="right">Đã thanh toán:</td><td class="right">{{ number_format($invoice->paid_amount, 0, ',', '.') }} đ</td></tr>
  </table>

  <table class="sign">
    <tr>
      <td><strong>Người nộp tiền</strong><br><span class="muted">(Ký, ghi rõ họ tên)</span></td>
      <td><strong>Thu ngân</strong><br><span class="muted">(Ký, ghi rõ họ tên)</span><br><br><br>{{ $invoice->cashier?->name }}</td>
    </tr>
  </table>

  <div class="center muted" style="margin-top:6px">Cảm ơn quý khách đã tin tưởng {{ config('clinic.name') }}.</div>
</body>
</html>
