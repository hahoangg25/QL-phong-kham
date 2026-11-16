@extends('admin.layouts.app')

@section('title', 'Thẻ kho '.$medicine->fullName())
@section('page_title', 'Thẻ kho: '.$medicine->fullName())
@section('breadcrumbs')
  <a href="{{ route('staff.inventory.index') }}">Tồn kho</a> / {{ $medicine->code }}
@endsection

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center">
          <span>Thông tin thuốc</span>
          <a href="{{ route('staff.medicines.edit', $medicine) }}" class="btn btn-sm btn-outline-primary ms-auto"><i class="bi bi-pencil me-1"></i>Sửa</a>
        </div>
        <div class="card-body row g-3">
          <div class="col-md-4"><div class="text-muted small">Mã thuốc</div><div class="fw-semibold">{{ $medicine->code }}</div></div>
          <div class="col-md-8"><div class="text-muted small">Hoạt chất</div><div>{{ $medicine->active_ingredient ?: '-' }}</div></div>
          <div class="col-md-4"><div class="text-muted small">Dạng bào chế</div><div>{{ $medicine->dosage_form ?: '-' }}</div></div>
          <div class="col-md-4"><div class="text-muted small">Đơn vị / đường dùng</div><div>{{ $medicine->unit }} · {{ $medicine->route }}</div></div>
          <div class="col-md-4"><div class="text-muted small">Giá bán</div><div>{{ money($medicine->price) }}</div></div>
          <div class="col-md-4"><div class="text-muted small">Hãng sản xuất</div><div>{{ $medicine->manufacturer ?: '-' }}</div></div>
          <div class="col-md-4"><div class="text-muted small">Trạng thái</div>
            <div>@if ($medicine->is_active)<span class="badge bg-success">Đang sử dụng</span>@else<span class="badge bg-secondary">Ngừng sử dụng</span>@endif</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card stat-card mb-3">
        <div class="stat-icon {{ $medicine->isLowStock() ? 'soft-danger' : 'soft-success' }}"><i class="bi bi-box-seam"></i></div>
        <div>
          <div class="stat-value">{{ number_format($medicine->usableStock()) }} <small class="fs-6 text-muted">{{ $medicine->unit }}</small></div>
          <div class="stat-label">Tồn còn hạn sử dụng</div>
        </div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon soft-warning"><i class="bi bi-flag"></i></div>
        <div>
          <div class="stat-value">{{ number_format($medicine->min_stock) }}</div>
          <div class="stat-label">Định mức tồn tối thiểu @if ($medicine->isLowStock())<span class="badge bg-danger ms-1">Dưới định mức</span>@endif</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">Các lô thuốc (sắp xếp theo hạn dùng - FEFO)</div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Số lô</th>
            <th>Hạn dùng</th>
            <th>Phiếu nhập</th>
            <th class="text-end">SL nhập</th>
            <th class="text-end">Còn tồn</th>
            <th class="text-end">Giá nhập</th>
            <th style="min-width:330px">Xuất hủy / Kiểm kê</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($batches as $batch)
            <tr class="{{ $batch->remaining === 0 ? 'text-muted' : '' }}">
              <td class="fw-semibold">{{ $batch->batch_no }}</td>
              <td>
                {{ $batch->expiry_date->format('d/m/Y') }}
                @if ($batch->isExpired())
                  <span class="badge bg-danger">Hết hạn</span>
                @elseif ($batch->isNearExpiry())
                  <span class="badge bg-warning text-dark">Sắp hết hạn</span>
                @endif
              </td>
              <td class="small">
                @if ($batch->receipt)
                  <a href="{{ route('staff.receipts.show', $batch->receipt) }}">{{ $batch->receipt->code }}</a>
                  <div class="text-muted">{{ $batch->receipt->received_date->format('d/m/Y') }}</div>
                @else - @endif
              </td>
              <td class="text-end">{{ number_format($batch->quantity) }}</td>
              <td class="text-end fw-semibold">{{ number_format($batch->remaining) }}</td>
              <td class="text-end">{{ money($batch->import_price) }}</td>
              <td>
                @if ($batch->remaining > 0)
                  <form method="POST" action="{{ route('staff.inventory.dispose', $batch) }}" class="d-flex gap-1 mb-1" data-confirm="Xác nhận xuất hủy thuốc lô {{ $batch->batch_no }}?">
                    @csrf
                    <input type="number" name="quantity" min="1" max="{{ $batch->remaining }}" value="{{ $batch->isExpired() ? $batch->remaining : '' }}" class="form-control form-control-sm" style="width:80px" placeholder="SL" required>
                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Lý do hủy" value="{{ $batch->isExpired() ? 'Thuốc hết hạn sử dụng' : '' }}" required>
                    <button class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-trash"></i> Hủy</button>
                  </form>
                @endif
                <form method="POST" action="{{ route('staff.inventory.adjust', $batch) }}" class="d-flex gap-1">
                  @csrf
                  <input type="number" name="actual" min="0" value="{{ $batch->remaining }}" class="form-control form-control-sm" style="width:80px" title="Số lượng thực tế" required>
                  <input type="text" name="reason" class="form-control form-control-sm" placeholder="Lý do kiểm kê" required>
                  <button class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-clipboard-check"></i> Kiểm kê</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i>Chưa có lô thuốc nào.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Biến động kho gần đây</div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Thời gian</th>
            <th>Loại</th>
            <th>Lô</th>
            <th class="text-end">Số lượng</th>
            <th>Tham chiếu</th>
            <th>Ghi chú</th>
            <th>Người thực hiện</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($transactions as $t)
            @php
              $typeColor = ['import' => 'success', 'dispense' => 'primary', 'dispose' => 'danger', 'adjust' => 'warning'][$t->type] ?? 'secondary';
              $ref = $t->reference;
            @endphp
            <tr>
              <td class="text-nowrap">{{ $t->created_at?->format('H:i d/m/Y') }}</td>
              <td><span class="badge bg-{{ $typeColor }}-subtle text-{{ $typeColor }}">{{ $t->typeLabel() }}</span></td>
              <td>{{ $t->batch?->batch_no ?? '-' }}</td>
              <td class="text-end fw-semibold {{ $t->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $t->quantity > 0 ? '+' : '' }}{{ number_format($t->quantity) }}</td>
              <td class="small">
                @if ($ref instanceof \App\Models\Clinical\Prescription)
                  <a href="{{ route('staff.pharmacy.show', $ref) }}">Đơn {{ $ref->code }}</a>
                @elseif ($ref instanceof \App\Models\Pharmacy\StockReceipt)
                  <a href="{{ route('staff.receipts.show', $ref) }}">Phiếu {{ $ref->code }}</a>
                @else
                  -
                @endif
              </td>
              <td class="small">{{ $t->note }}</td>
              <td class="small">{{ $t->user?->name }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-3">Chưa có biến động.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($transactions->hasPages())
      <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
  </div>
@endsection
