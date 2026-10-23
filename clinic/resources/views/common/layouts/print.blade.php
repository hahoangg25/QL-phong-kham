<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>@yield('title') - {{ config('clinic.name') }}</title>
  <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <style>
    body { font-family: "Times New Roman", serif; font-size: 15px; color: #000; background: #e9ecef; }
    .sheet { background: #fff; width: 210mm; min-height: 148mm; margin: 20px auto; padding: 14mm 16mm; box-shadow: 0 0 12px rgba(0,0,0,.12); }
    .sheet.a5 { width: 148mm; min-height: 105mm; padding: 10mm; }
    .clinic-head { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
    .clinic-head h1 { font-size: 18px; font-weight: 700; margin: 0; text-transform: uppercase; }
    .doc-title { text-align: center; font-size: 22px; font-weight: 700; text-transform: uppercase; margin: 10px 0 4px; }
    table.print-table { width: 100%; border-collapse: collapse; }
    table.print-table th, table.print-table td { border: 1px solid #333; padding: 5px 7px; vertical-align: top; }
    table.print-table th { background: #f1f1f1; text-align: center; }
    .sign-box { text-align: center; min-height: 110px; }
    .toolbar { text-align: center; margin: 16px 0 0; }
    @media print {
      body { background: #fff; }
      .sheet { box-shadow: none; margin: 0; width: auto; min-height: auto; }
      .toolbar { display: none; }
      @page { margin: 10mm; }
    }
  </style>
  @stack('styles')
</head>

<body>
  <div class="toolbar">
    <button class="btn btn-primary btn-sm" onclick="window.print()">In / Lưu PDF</button>
    <button class="btn btn-outline-secondary btn-sm" onclick="history.length > 1 ? history.back() : window.close()">Quay lại</button>
  </div>

  <div class="sheet @yield('sheet_class')">
    <div class="clinic-head d-flex justify-content-between align-items-end">
      <div>
        <h1>{{ config('clinic.name') }}</h1>
        <div>{{ config('clinic.address') }}</div>
        <div>Hotline: {{ config('clinic.hotline') }} · {{ config('clinic.email') }}</div>
      </div>
      <div class="text-end small">@yield('head_right')</div>
    </div>

    @yield('content')
  </div>

  @if (request('auto'))
    <script>window.addEventListener('load', () => window.print());</script>
  @endif
</body>

</html>
