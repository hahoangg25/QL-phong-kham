@extends('admin.layouts.app')

@section('title', 'Báo cáo thống kê')
@section('page_title', 'Báo cáo thống kê')
@section('breadcrumbs', 'Quản trị / Báo cáo')

@section('content')
  <form class="filter-bar row g-2 align-items-end" method="GET" id="reportFilter">
    <div class="col-md-2">
      <label class="form-label">Từ ngày</label>
      <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label">Đến ngày</label>
      <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label">Chuyên khoa</label>
      <select name="specialty_id" class="form-select">
        <option value="">Tất cả</option>
        @foreach ($specialties as $sp)
          <option value="{{ $sp->id }}" @selected(request('specialty_id') == $sp->id)>{{ $sp->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Bác sĩ</label>
      <select name="doctor_id" class="form-select">
        <option value="">Tất cả</option>
        @foreach ($doctors as $d)
          <option value="{{ $d->id }}" @selected(request('doctor_id') == $d->id)>{{ $d->display_name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Xem</button>
      <div class="dropdown ms-auto">
        <button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Xuất</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="{{ route('staff.admin.reports.export', request()->query() + ['format' => 'csv']) }}"><i class="bi bi-filetype-csv me-2"></i>Excel (CSV)</a></li>
          <li><a class="dropdown-item" href="{{ route('staff.admin.reports.export', request()->query() + ['format' => 'pdf']) }}"><i class="bi bi-filetype-pdf me-2"></i>PDF</a></li>
        </ul>
      </div>
    </div>
    <div class="col-12 d-flex flex-wrap gap-1">
      @foreach (['7' => '7 ngày', '30' => '30 ngày', '90' => '90 ngày'] as $days => $label)
        <a href="{{ route('staff.admin.reports.index', array_merge(request()->except('from', 'to'), ['from' => today()->subDays($days - 1)->toDateString(), 'to' => today()->toDateString()])) }}" class="btn btn-sm btn-light">{{ $label }}</a>
      @endforeach
      <a href="{{ route('staff.admin.reports.index', array_merge(request()->except('from', 'to'), ['from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()])) }}" class="btn btn-sm btn-light">Tháng này</a>
      <a href="{{ route('staff.admin.reports.index', array_merge(request()->except('from', 'to'), ['from' => today()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => today()->subMonthNoOverflow()->endOfMonth()->toDateString()])) }}" class="btn btn-sm btn-light">Tháng trước</a>
      @if ($filterLabel)<span class="ms-auto small text-muted align-self-center">{{ $filterLabel }}</span>@endif
    </div>
  </form>

  {{-- KPI --}}
  <div class="row g-3 mb-3">
    @php
      $cards = [
          ['bi-clipboard2-check', 'soft-primary', number_format($kpi['visits']), 'Lượt khám hoàn thành', 'Vãng lai: '.number_format($kpi['walk_in'])],
          ['bi-cash-stack', 'soft-success', money($kpi['revenue']), 'Doanh thu thực thu', 'TB '.money($kpi['avg_revenue']).'/lượt'],
          ['bi-calendar2-week', 'soft-info', number_format($kpi['appointments']), 'Lịch hẹn', 'Đặt online '.$kpi['online_rate'].'%'],
          ['bi-person-x', 'soft-danger', $kpi['no_show_rate'].'%', 'Tỷ lệ vắng mặt', $kpi['no_shows'].' lịch no-show'],
          ['bi-calendar-x', 'soft-warning', $kpi['cancel_rate'].'%', 'Tỷ lệ hủy lịch', $kpi['cancelled'].' lịch đã hủy'],
          ['bi-globe2', 'soft-purple', $kpi['online_rate'].'%', 'Tỷ lệ đặt online', 'Trên tổng lịch hẹn'],
      ];
    @endphp
    @foreach ($cards as [$icon, $soft, $value, $label, $sub])
      <div class="col-sm-6 col-xl-2">
        <div class="card stat-card h-100">
          <div class="stat-icon {{ $soft }}"><i class="bi {{ $icon }}"></i></div>
          <div class="min-w-0">
            <div class="stat-value" style="font-size:20px">{{ $value }}</div>
            <div class="stat-label">{{ $label }}</div>
            <div class="small text-muted">{{ $sub }}</div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-8">
      <div class="card h-100">
        <div class="card-header">Lượt khám và doanh thu theo ngày</div>
        <div class="card-body"><canvas id="dailyChart" height="120"></canvas></div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header">Lượt khám theo chuyên khoa</div>
        <div class="card-body d-flex align-items-center justify-content-center">
          @if ($bySpecialty->isEmpty())
            <div class="empty-state"><i class="bi bi-pie-chart"></i>Chưa có dữ liệu</div>
          @else
            <canvas id="specialtyChart" height="230"></canvas>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header">Cơ cấu doanh thu (hóa đơn đã thu)</div>
        <div class="card-body">
          <canvas id="itemTypeChart" height="200"></canvas>
          <table class="table table-sm mt-3 mb-0">
            @foreach ($byItemType as $item)
              <tr><td>{{ $item['label'] }}</td><td class="text-end fw-semibold">{{ money($item['amount']) }}</td></tr>
            @endforeach
          </table>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header">Trạng thái lịch hẹn</div>
        <div class="card-body">
          @forelse ($kpi['status_breakdown'] as $label => $count)
            @php $pct = $kpi['appointments'] ? round($count * 100 / $kpi['appointments'], 1) : 0; @endphp
            <div class="mb-2">
              <div class="d-flex justify-content-between small"><span>{{ $label }}</span><span class="text-muted">{{ $count }} ({{ $pct }}%)</span></div>
              <div class="progress" style="height:6px"><div class="progress-bar" style="width:{{ $pct }}%"></div></div>
            </div>
          @empty
            <div class="empty-state"><i class="bi bi-calendar"></i>Chưa có lịch hẹn</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header">Top 10 chẩn đoán (bệnh chính)</div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Mã</th><th>Bệnh</th><th class="text-end">Số ca</th></tr></thead>
            <tbody>
              @forelse ($topDiagnoses as $d)
                <tr>
                  <td><span class="badge bg-primary-subtle text-primary-emphasis">{{ $d['code'] }}</span></td>
                  <td class="small">{{ $d['name'] }}</td>
                  <td class="text-end fw-semibold">{{ $d['count'] }}</td>
                </tr>
              @empty
                <tr><td colspan="3"><div class="empty-state">Chưa có dữ liệu</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Hiệu suất bác sĩ</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Bác sĩ</th>
            <th>Chuyên khoa</th>
            <th class="text-end">Lượt khám</th>
            <th class="text-end">Doanh thu</th>
            <th class="text-center">Đánh giá</th>
            <th class="text-center">Tỷ lệ vắng mặt</th>
            <th class="text-center">Thời gian khám TB</th>
          </tr>
        </thead>
        <tbody>
          @php $maxVisits = max(1, $doctorStats->max('visits') ?? 1); @endphp
          @forelse ($doctorStats as $d)
            <tr>
              <td class="fw-semibold">{{ $d['name'] }}</td>
              <td>{{ $d['specialty'] }}</td>
              <td class="text-end" style="min-width:150px">
                <div class="d-flex align-items-center gap-2 justify-content-end">
                  <div class="progress flex-grow-1" style="height:6px;max-width:90px"><div class="progress-bar" style="width:{{ round($d['visits'] * 100 / $maxVisits) }}%"></div></div>
                  <span class="fw-semibold">{{ $d['visits'] }}</span>
                </div>
              </td>
              <td class="text-end">{{ money($d['revenue']) }}</td>
              <td class="text-center">
                @if ($d['rating_count'])
                  <i class="bi bi-star-fill text-warning"></i> {{ number_format($d['rating'], 1) }} <small class="text-muted">({{ $d['rating_count'] }})</small>
                @else
                  <small class="text-muted">—</small>
                @endif
              </td>
              <td class="text-center">
                <span class="badge {{ $d['no_show_rate'] >= 15 ? 'bg-danger' : ($d['no_show_rate'] >= 8 ? 'bg-warning text-dark' : 'bg-success-subtle text-success-emphasis') }}">{{ $d['no_show_rate'] }}%</span>
              </td>
              <td class="text-center">{{ $d['avg_minutes'] !== null ? $d['avg_minutes'].' phút' : '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty-state">Không có dữ liệu</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
    (function () {
      if (!window.Chart) return;
      Chart.defaults.font.family = 'Roboto, system-ui, sans-serif';
      Chart.defaults.color = '#667085';
      const palette = ['#1977cc', '#3fbbc0', '#f59f00', '#6f42c1', '#e8590c', '#2f9e44', '#d6336c', '#1098ad', '#5c7cfa', '#868e96'];
      const daily = @json($daily);
      const money = (v) => new Intl.NumberFormat('vi-VN', { notation: v >= 1e6 ? 'compact' : 'standard' }).format(v);

      new Chart(document.getElementById('dailyChart'), {
        data: {
          labels: daily.map(d => d.label),
          datasets: [
            { type: 'bar', label: 'Doanh thu (đ)', data: daily.map(d => d.revenue), backgroundColor: 'rgba(63,187,192,.35)', borderRadius: 4, yAxisID: 'y1', order: 2 },
            { type: 'line', label: 'Lượt khám', data: daily.map(d => d.visits), borderColor: '#1977cc', backgroundColor: '#1977cc', tension: .3, pointRadius: 2, yAxisID: 'y', order: 1 },
          ],
        },
        options: {
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: (c) => c.dataset.yAxisID === 'y1' ? ' Doanh thu: ' + new Intl.NumberFormat('vi-VN').format(c.raw) + ' đ' : ' Lượt khám: ' + c.raw } },
          },
          scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Lượt khám' } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: money } },
            x: { grid: { display: false } },
          },
        },
      });

      const sp = @json($bySpecialty);
      const spEl = document.getElementById('specialtyChart');
      if (spEl) {
        new Chart(spEl, {
          type: 'doughnut',
          data: { labels: sp.map(s => s.name), datasets: [{ data: sp.map(s => s.visits), backgroundColor: palette, borderWidth: 1 }] },
          options: { cutout: '60%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } },
        });
      }

      const items = @json($byItemType);
      new Chart(document.getElementById('itemTypeChart'), {
        type: 'pie',
        data: { labels: items.map(i => i.label), datasets: [{ data: items.map(i => i.amount), backgroundColor: ['#1977cc', '#3fbbc0', '#f59f00'] }] },
        options: {
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12 } },
            tooltip: { callbacks: { label: (c) => ' ' + c.label + ': ' + new Intl.NumberFormat('vi-VN').format(c.raw) + ' đ' } },
          },
        },
      });
    })();
  </script>
@endpush
