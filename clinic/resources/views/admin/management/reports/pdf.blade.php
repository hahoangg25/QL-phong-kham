<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Báo cáo hoạt động phòng khám</title>
  <style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; color: #222; }
    h1 { font-size: 17px; margin: 0 0 4px; text-transform: uppercase; }
    h2 { font-size: 12.5px; margin: 16px 0 6px; color: #0f2341; border-bottom: 1px solid #1977cc; padding-bottom: 3px; }
    .muted { color: #666; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #bbb; padding: 4px 6px; }
    th { background: #eef3fa; text-align: left; }
    .right { text-align: right; }
    .center { text-align: center; }
    .kpi td { border: 1px solid #d6e4f5; width: 16.6%; text-align: center; padding: 8px 4px; }
    .kpi .v { font-size: 14px; font-weight: bold; color: #0f2341; }
    .two { width: 100%; }
    .two td.col { border: 0; vertical-align: top; padding: 0 6px 0 0; width: 50%; }
  </style>
</head>
<body>
  <table style="border:0; margin-bottom:6px">
    <tr>
      <td style="border:0">
        <strong>{{ config('clinic.name') }}</strong><br>
        <span class="muted">{{ config('clinic.address') }} · Hotline {{ config('clinic.hotline') }}</span>
      </td>
      <td style="border:0" class="right muted">Xuất lúc {{ now()->format('H:i d/m/Y') }}</td>
    </tr>
  </table>

  <h1>Báo cáo hoạt động phòng khám</h1>
  <div class="muted">Từ ngày {{ $from->format('d/m/Y') }} đến ngày {{ $to->format('d/m/Y') }}{{ $filterLabel ? ' · '.$filterLabel : '' }}</div>

  <h2>Chỉ số tổng hợp</h2>
  <table class="kpi">
    <tr>
      <td><div class="v">{{ number_format($kpi['visits']) }}</div>Lượt khám</td>
      <td><div class="v">{{ money($kpi['revenue']) }}</div>Doanh thu thực thu</td>
      <td><div class="v">{{ money($kpi['avg_revenue']) }}</div>Doanh thu TB/lượt</td>
      <td><div class="v">{{ number_format($kpi['appointments']) }}</div>Lịch hẹn</td>
      <td><div class="v">{{ $kpi['no_show_rate'] }}%</div>Tỷ lệ vắng mặt</td>
      <td><div class="v">{{ $kpi['online_rate'] }}%</div>Đặt online</td>
    </tr>
  </table>

  <table class="two" style="border:0">
    <tr>
      <td class="col">
        <h2>Theo chuyên khoa</h2>
        <table>
          <tr><th>Chuyên khoa</th><th class="right">Lượt khám</th><th class="right">Doanh thu</th></tr>
          @forelse ($bySpecialty as $s)
            <tr><td>{{ $s['name'] }}</td><td class="right">{{ $s['visits'] }}</td><td class="right">{{ money($s['revenue']) }}</td></tr>
          @empty
            <tr><td colspan="3" class="center muted">Không có dữ liệu</td></tr>
          @endforelse
        </table>

        <h2>Cơ cấu doanh thu</h2>
        <table>
          @foreach ($byItemType as $item)
            <tr><td>{{ $item['label'] }}</td><td class="right">{{ money($item['amount']) }}</td></tr>
          @endforeach
        </table>
      </td>
      <td class="col">
        <h2>Top chẩn đoán</h2>
        <table>
          <tr><th>Mã</th><th>Bệnh</th><th class="right">Số ca</th></tr>
          @forelse ($topDiagnoses as $d)
            <tr><td>{{ $d['code'] }}</td><td>{{ $d['name'] }}</td><td class="right">{{ $d['count'] }}</td></tr>
          @empty
            <tr><td colspan="3" class="center muted">Không có dữ liệu</td></tr>
          @endforelse
        </table>
      </td>
    </tr>
  </table>

  <h2>Hiệu suất bác sĩ</h2>
  <table>
    <tr>
      <th>Bác sĩ</th><th>Chuyên khoa</th><th class="right">Lượt khám</th><th class="right">Doanh thu</th>
      <th class="center">Đánh giá</th><th class="center">Vắng mặt</th><th class="center">Khám TB</th>
    </tr>
    @foreach ($doctorStats as $d)
      <tr>
        <td>{{ $d['name'] }}</td><td>{{ $d['specialty'] }}</td>
        <td class="right">{{ $d['visits'] }}</td><td class="right">{{ money($d['revenue']) }}</td>
        <td class="center">{{ $d['rating_count'] ? number_format($d['rating'], 1).' ('.$d['rating_count'].')' : '-' }}</td>
        <td class="center">{{ $d['no_show_rate'] }}%</td>
        <td class="center">{{ $d['avg_minutes'] !== null ? $d['avg_minutes'].' phút' : '-' }}</td>
      </tr>
    @endforeach
  </table>

  <h2>Theo ngày</h2>
  <table>
    <tr><th>Ngày</th><th class="right">Lượt khám</th><th class="right">Doanh thu</th><th>Ngày</th><th class="right">Lượt khám</th><th class="right">Doanh thu</th></tr>
    @foreach ($daily->chunk(2) as $pair)
      <tr>
        @foreach ($pair as $d)
          <td>{{ \Carbon\Carbon::parse($d['date'])->format('d/m/Y') }}</td><td class="right">{{ $d['visits'] }}</td><td class="right">{{ money($d['revenue']) }}</td>
        @endforeach
        @if ($pair->count() < 2)<td></td><td></td><td></td>@endif
      </tr>
    @endforeach
  </table>
</body>
</html>
