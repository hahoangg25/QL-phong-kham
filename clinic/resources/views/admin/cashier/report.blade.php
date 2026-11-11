@extends('admin.layouts.app')

@section('title', 'Báo cáo thu theo ca')
@section('page_title', 'Báo cáo thu theo ca')
@section('breadcrumbs')
  <a href="{{ route('staff.cashier.index') }}">Thu ngân</a> / Báo cáo thu
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
    <div class="col-md-3">
      <label class="form-label">Người thu</label>
      <select name="cashier_id" class="form-select">
        <option value="">Tất cả</option>
        @foreach ($cashiers as $c)
          <option value="{{ $c->id }}" @selected((string) request('cashier_id') === (string) $c->id)>{{ $c->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Xem</button>
      <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
    </div>
  </form>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="stat-icon soft-primary"><i class="bi bi-receipt"></i></div>
        <div><div class="stat-value">{{ $summary['count'] }}</div><div class="stat-label">Giao dịch</div></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="stat-icon soft-success"><i class="bi bi-arrow-down-circle"></i></div>
        <div><div class="stat-value">{{ money($summary['income']) }}</div><div class="stat-label">Tổng thu</div></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="stat-icon soft-danger"><i class="bi bi-arrow-up-circle"></i></div>
        <div><div class="stat-value">{{ money($summary['refund']) }}</div><div class="stat-label">Hoàn tiền</div></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="stat-icon soft-purple"><i class="bi bi-wallet2"></i></div>
        <div><div class="stat-value">{{ money($summary['net']) }}</div><div class="stat-label">Thực thu</div></div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">Theo người thu</div>
        <table class="table table-sm mb-0">
          <thead><tr><th>Người thu</th><th class="text-center">GD</th><th class="text-end">Thực thu</th></tr></thead>
          <tbody>
            @forelse ($byCashier as $name => $row)
              <tr><td>{{ $name }}</td><td class="text-center">{{ $row['count'] }}</td><td class="text-end">{{ money($row['total']) }}</td></tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted py-3">Không có dữ liệu</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">Theo phương thức</div>
        <table class="table table-sm mb-0">
          <thead><tr><th>Phương thức</th><th class="text-center">GD</th><th class="text-end">Thực thu</th></tr></thead>
          <tbody>
            @forelse ($byMethod as $name => $row)
              <tr><td>{{ $name }}</td><td class="text-center">{{ $row['count'] }}</td><td class="text-end">{{ money($row['total']) }}</td></tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted py-3">Không có dữ liệu</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">Theo ngày</div>
        <table class="table table-sm mb-0">
          <thead><tr><th>Ngày</th><th class="text-end">Thu</th><th class="text-end">Hoàn</th></tr></thead>
          <tbody>
            @forelse ($byDay as $day => $row)
              <tr>
                <td>{{ \Carbon\Carbon::parse($day)->format('d/m/Y') }}</td>
                <td class="text-end">{{ money($row['income']) }}</td>
                <td class="text-end text-danger">{{ $row['refund'] ? money($row['refund']) : '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted py-3">Không có dữ liệu</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Chi tiết giao dịch ({{ $from->format('d/m/Y') }}@if (! $from->isSameDay($to)) - {{ $to->format('d/m/Y') }}@endif)</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Mã GD</th>
            <th>Thời gian</th>
            <th>Hóa đơn / lịch hẹn</th>
            <th>Bệnh nhân</th>
            <th>Phương thức</th>
            <th>Loại</th>
            <th class="text-end">Số tiền</th>
            <th>Người thu</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($payments as $p)
            <tr>
              <td class="small">{{ $p->code }}</td>
              <td>{{ $p->paid_at->format('H:i d/m') }}</td>
              <td>
                @if ($p->invoice)
                  <a href="{{ route('staff.cashier.show', $p->invoice) }}">{{ $p->invoice->code }}</a>
                @elseif ($p->appointment)
                  <span class="text-muted">Lịch hẹn {{ $p->appointment->code }}</span>
                @else
                  -
                @endif
              </td>
              <td>{{ $p->patient?->full_name }}</td>
              <td>{{ $p->method?->label() }}</td>
              <td>{{ $p->typeLabel() }}</td>
              <td class="text-end {{ $p->type === 'refund' ? 'text-danger' : '' }}">{{ $p->type === 'refund' ? '-' : '' }}{{ money($p->amount) }}</td>
              <td class="small">{{ $p->receiver?->name ?? 'Online' }}</td>
            </tr>
          @empty
            <tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i>Không có giao dịch trong khoảng thời gian này.</div></td></tr>
          @endforelse
        </tbody>
        @if ($payments->isNotEmpty())
          <tfoot>
            <tr>
              <td colspan="6" class="text-end fw-bold">Thực thu</td>
              <td class="text-end fw-bold">{{ money($summary['net']) }}</td>
              <td></td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>
  </div>
@endsection
