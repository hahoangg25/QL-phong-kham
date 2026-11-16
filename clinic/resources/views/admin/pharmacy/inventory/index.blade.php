@extends('admin.layouts.app')

@section('title', 'Tồn kho & cảnh báo')
@section('page_title', 'Tồn kho & cảnh báo')
@section('breadcrumbs', 'Dược & kho / Tồn kho')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center text-danger">
          <i class="bi bi-graph-down-arrow me-2"></i>Tồn dưới định mức
          <span class="badge bg-danger ms-auto">{{ $alerts['low']->count() }}</span>
        </div>
        <ul class="list-group list-group-flush small" style="max-height:260px;overflow-y:auto">
          @forelse ($alerts['low'] as $m)
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <a href="{{ route('staff.inventory.show', $m) }}">{{ $m->fullName() }}</a>
              <span><strong class="text-danger">{{ $m->usableStock() }}</strong> / {{ $m->min_stock }} {{ $m->unit }}</span>
            </li>
          @empty
            <li class="list-group-item text-muted">Không có thuốc dưới định mức.</li>
          @endforelse
        </ul>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center text-warning">
          <i class="bi bi-hourglass-bottom me-2"></i>Sắp hết hạn ({{ setting('expiry_warning_days') }} ngày)
          <span class="badge bg-warning text-dark ms-auto">{{ $alerts['nearExpiry']->count() }}</span>
        </div>
        <ul class="list-group list-group-flush small" style="max-height:260px;overflow-y:auto">
          @forelse ($alerts['nearExpiry'] as $b)
            <li class="list-group-item">
              <div class="d-flex justify-content-between">
                <a href="{{ route('staff.inventory.show', $b->medicine_id) }}">{{ $b->medicine->fullName() }}</a>
                <span>{{ $b->remaining }} {{ $b->medicine->unit }}</span>
              </div>
              <div class="text-muted">Lô {{ $b->batch_no }} · HSD {{ $b->expiry_date->format('d/m/Y') }} (còn {{ (int) today()->diffInDays($b->expiry_date) }} ngày)</div>
            </li>
          @empty
            <li class="list-group-item text-muted">Không có lô sắp hết hạn.</li>
          @endforelse
        </ul>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center text-danger">
          <i class="bi bi-x-octagon me-2"></i>Đã hết hạn còn tồn
          <span class="badge bg-danger ms-auto">{{ $alerts['expired']->count() }}</span>
        </div>
        <ul class="list-group list-group-flush small" style="max-height:260px;overflow-y:auto">
          @forelse ($alerts['expired'] as $b)
            <li class="list-group-item">
              <div class="d-flex justify-content-between">
                <a href="{{ route('staff.inventory.show', $b->medicine_id) }}">{{ $b->medicine->fullName() }}</a>
                <span class="text-danger">{{ $b->remaining }} {{ $b->medicine->unit }}</span>
              </div>
              <div class="text-muted">Lô {{ $b->batch_no }} · hết hạn {{ $b->expiry_date->format('d/m/Y') }} — cần xuất hủy</div>
            </li>
          @empty
            <li class="list-group-item text-muted">Không có lô hết hạn còn tồn.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>

  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-5">
      <label class="form-label">Tìm thuốc</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên thuốc, hoạt chất hoặc mã">
    </div>
    <div class="col-md-3">
      <label class="form-label">Lọc</label>
      <select name="filter" class="form-select">
        <option value="">Tất cả</option>
        <option value="low" @selected(request('filter') === 'low')>Dưới định mức</option>
      </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Lọc</button>
      <a href="{{ route('staff.receipts.create') }}" class="btn btn-outline-success ms-auto"><i class="bi bi-box-arrow-in-down me-1"></i>Nhập kho</a>
      <a href="{{ route('staff.inventory.report') }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-bar-graph me-1"></i>Báo cáo</a>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Mã</th>
            <th>Thuốc</th>
            <th>Đơn vị</th>
            <th class="text-end">Số lượng tồn</th>
            <th class="text-end">Định mức</th>
            <th>Hạn gần nhất</th>
            <th>Tình trạng</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($medicines as $m)
            @php
              $nearest = $m->nearest_expiry ? \Carbon\Carbon::parse($m->nearest_expiry) : null;
              $near = $nearest && $nearest->lte(today()->addDays((int) setting('expiry_warning_days')));
            @endphp
            <tr class="{{ $m->is_active ? '' : 'text-muted' }}">
              <td>{{ $m->code }}</td>
              <td>
                <div class="fw-semibold">{{ $m->fullName() }}</div>
                <div class="small text-muted">{{ $m->active_ingredient }}</div>
              </td>
              <td>{{ $m->unit }}</td>
              <td class="text-end fw-semibold {{ $m->isLowStock() ? 'text-danger' : '' }}">{{ number_format($m->usableStock()) }}</td>
              <td class="text-end">{{ number_format($m->min_stock) }}</td>
              <td>
                @if ($nearest)
                  {{ $nearest->format('d/m/Y') }}
                  @if ($near)<span class="badge bg-warning text-dark">Sắp hết hạn</span>@endif
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td>
                @if ($m->usableStock() === 0)
                  <span class="badge bg-danger">Hết hàng</span>
                @elseif ($m->isLowStock())
                  <span class="badge bg-danger-subtle text-danger">Dưới định mức</span>
                @else
                  <span class="badge bg-success-subtle text-success">Đủ hàng</span>
                @endif
              </td>
              <td class="text-end"><a href="{{ route('staff.inventory.show', $m) }}" class="btn btn-sm btn-outline-secondary">Thẻ kho</a></td>
            </tr>
          @empty
            <tr><td colspan="8"><div class="empty-state"><i class="bi bi-boxes"></i>Không tìm thấy thuốc.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($medicines->hasPages())
      <div class="card-footer">{{ $medicines->links() }}</div>
    @endif
  </div>
@endsection
