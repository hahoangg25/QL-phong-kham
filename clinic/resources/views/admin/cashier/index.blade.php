@extends('admin.layouts.app')

@section('title', 'Thu ngân')
@section('page_title', 'Thu ngân')
@section('breadcrumbs', 'Thu ngân / Danh sách hóa đơn')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="stat-icon soft-warning"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <div class="stat-value">{{ number_format($stats['unpaid_count']) }}</div>
          <div class="stat-label">Hóa đơn chờ thu</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="stat-icon soft-danger"><i class="bi bi-wallet2"></i></div>
        <div>
          <div class="stat-value">{{ money($stats['unpaid_total']) }}</div>
          <div class="stat-label">Tổng tiền chưa thu</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="stat-icon soft-success"><i class="bi bi-cash-stack"></i></div>
        <div>
          <div class="stat-value">{{ money($stats['paid_today']) }}</div>
          <div class="stat-label">Thực thu hôm nay ({{ $stats['paid_today_count'] }} hóa đơn)</div>
        </div>
      </div>
    </div>
  </div>

  <ul class="nav nav-tabs mb-3">
    @foreach (['unpaid' => 'Chờ thanh toán', 'paid' => 'Đã thu hôm nay', 'closed' => 'Đã hủy / hoàn tiền', 'all' => 'Tất cả'] as $key => $label)
      <li class="nav-item">
        <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('staff.cashier.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}">
          {{ $label }}
          @if ($key === 'unpaid' && $stats['unpaid_count'])
            <span class="badge bg-warning text-dark ms-1">{{ $stats['unpaid_count'] }}</span>
          @endif
        </a>
      </li>
    @endforeach
  </ul>

  <form class="filter-bar row g-2 align-items-end" method="GET">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="col-md-5">
      <label class="form-label">Tìm kiếm</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Mã hóa đơn, tên, SĐT hoặc mã bệnh nhân">
    </div>
    <div class="col-md-3">
      <label class="form-label">Ngày lập</label>
      <input type="date" name="date" value="{{ request('date') }}" class="form-control">
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Lọc</button>
      <a href="{{ route('staff.cashier.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary">Xóa lọc</a>
      <a href="{{ route('staff.cashier.report') }}" class="btn btn-outline-primary ms-auto"><i class="bi bi-bar-chart-line me-1"></i>Báo cáo ca</a>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Mã hóa đơn</th>
            <th>Bệnh nhân</th>
            <th>Bác sĩ / ngày khám</th>
            <th class="text-end">Tổng tiền</th>
            <th class="text-end">Còn phải thu</th>
            <th>Trạng thái</th>
            <th>Thu ngân</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($invoices as $invoice)
            <tr>
              <td>
                <a href="{{ route('staff.cashier.show', $invoice) }}" class="fw-semibold">{{ $invoice->code }}</a>
                <div class="small text-muted">{{ $invoice->created_at->format('H:i d/m/Y') }}</div>
              </td>
              <td>
                {{ $invoice->patient?->full_name }}
                <div class="small text-muted">{{ $invoice->patient?->code }} · {{ $invoice->patient?->phone }}</div>
              </td>
              <td>
                {{ $invoice->visit?->doctor?->display_name ?? '-' }}
                <div class="small text-muted">{{ $invoice->visit?->visit_date?->format('d/m/Y') }}</div>
              </td>
              <td class="text-end">{{ money($invoice->total) }}</td>
              <td class="text-end fw-semibold {{ $invoice->amountDue() > 0 && $invoice->status->value === 'unpaid' ? 'text-danger' : 'text-muted' }}">
                {{ $invoice->status->value === 'unpaid' ? money($invoice->amountDue()) : '-' }}
              </td>
              <td>{!! status_badge($invoice->status) !!}</td>
              <td class="small">
                {{ $invoice->cashier?->name ?? '-' }}
                @if ($invoice->paid_at)<div class="text-muted">{{ $invoice->paid_at->format('H:i d/m') }}</div>@endif
              </td>
              <td class="text-end text-nowrap">
                @if ($invoice->status->value === 'unpaid')
                  <a href="{{ route('staff.cashier.show', $invoice) }}" class="btn btn-sm btn-primary"><i class="bi bi-cash-coin me-1"></i>Thu tiền</a>
                @else
                  <a href="{{ route('staff.cashier.show', $invoice) }}" class="btn btn-sm btn-outline-secondary">Chi tiết</a>
                  <a href="{{ route('staff.cashier.print', $invoice) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="In"><i class="bi bi-printer"></i></a>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8">
                <div class="empty-state"><i class="bi bi-receipt"></i>Không có hóa đơn nào.</div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($invoices->hasPages())
      <div class="card-footer">{{ $invoices->links() }}</div>
    @endif
  </div>
@endsection
