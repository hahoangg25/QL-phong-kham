@extends('admin.layouts.app')

@section('title', 'Báo cáo nhập - xuất - tồn')
@section('page_title', 'Báo cáo nhập - xuất - tồn')
@section('breadcrumbs')
  <a href="{{ route('staff.inventory.index') }}">Tồn kho</a> / Báo cáo
@endsection

@section('content')
  <form class="filter-bar row g-2 align-items-end no-print" method="GET">
    <div class="col-md-3">
      <label class="form-label">Từ ngày</label>
      <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Đến ngày</label>
      <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
    </div>
    <div class="col-md-6 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Xem báo cáo</button>
      <a href="{{ route('staff.inventory.report', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'export' => 'csv']) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Xuất Excel (CSV)</a>
      <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
    </div>
  </form>

  <div class="card">
    <div class="card-header">Kỳ báo cáo: {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Mã</th>
            <th>Tên thuốc</th>
            <th>Đơn vị</th>
            <th class="text-end">Tồn đầu kỳ</th>
            <th class="text-end">Nhập</th>
            <th class="text-end">Xuất cấp phát</th>
            <th class="text-end">Xuất hủy</th>
            <th class="text-end">Điều chỉnh</th>
            <th class="text-end">Tồn cuối kỳ</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $r)
            <tr>
              <td>{{ $r['medicine']->code }}</td>
              <td><a href="{{ route('staff.inventory.show', $r['medicine']) }}">{{ $r['medicine']->fullName() }}</a></td>
              <td>{{ $r['medicine']->unit }}</td>
              <td class="text-end">{{ number_format($r['opening']) }}</td>
              <td class="text-end text-success">{{ $r['import'] ? number_format($r['import']) : '-' }}</td>
              <td class="text-end text-primary">{{ $r['dispense'] ? number_format($r['dispense']) : '-' }}</td>
              <td class="text-end text-danger">{{ $r['dispose'] ? number_format($r['dispose']) : '-' }}</td>
              <td class="text-end">{{ $r['adjust'] ? ($r['adjust'] > 0 ? '+' : '').number_format($r['adjust']) : '-' }}</td>
              <td class="text-end fw-semibold">{{ number_format($r['closing']) }}</td>
            </tr>
          @empty
            <tr><td colspan="9"><div class="empty-state"><i class="bi bi-file-earmark-bar-graph"></i>Không có biến động kho trong kỳ.</div></td></tr>
          @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
          <tfoot>
            <tr class="fw-bold">
              <td colspan="3" class="text-end">Tổng cộng</td>
              <td class="text-end">{{ number_format($totals['opening']) }}</td>
              <td class="text-end">{{ number_format($totals['import']) }}</td>
              <td class="text-end">{{ number_format($totals['dispense']) }}</td>
              <td class="text-end">{{ number_format($totals['dispose']) }}</td>
              <td class="text-end">{{ number_format($totals['adjust']) }}</td>
              <td class="text-end">{{ number_format($totals['closing']) }}</td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>
  </div>
@endsection
