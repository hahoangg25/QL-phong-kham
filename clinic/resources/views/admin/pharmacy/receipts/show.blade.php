@extends('admin.layouts.app')

@section('title', 'Phiếu nhập '.$receipt->code)
@section('page_title', 'Phiếu nhập '.$receipt->code)
@section('breadcrumbs')
  <a href="{{ route('staff.receipts.index') }}">Nhập kho</a> / {{ $receipt->code }}
@endsection

@section('content')
  <div class="card mb-3">
    <div class="card-body row g-3">
      <div class="col-md-3">
        <div class="text-muted small">Mã phiếu</div>
        <div class="fw-semibold">{{ $receipt->code }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Ngày nhập</div>
        <div>{{ $receipt->received_date->format('d/m/Y') }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Nhà cung cấp</div>
        <div>{{ $receipt->supplier }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Người lập</div>
        <div>{{ $receipt->creator?->name }} <span class="small text-muted">({{ $receipt->created_at->format('H:i d/m/Y') }})</span></div>
      </div>
      @if ($receipt->note)
        <div class="col-12">
          <div class="text-muted small">Ghi chú</div>
          <div>{{ $receipt->note }}</div>
        </div>
      @endif
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center">
      <span>Danh sách lô thuốc</span>
      <button type="button" class="btn btn-sm btn-outline-secondary ms-auto no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>In</button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Thuốc</th>
            <th>Số lô</th>
            <th>Hạn dùng</th>
            <th class="text-end">Số lượng</th>
            <th class="text-end">Còn tồn</th>
            <th class="text-end">Giá nhập</th>
            <th class="text-end">Thành tiền</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($receipt->batches as $i => $batch)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td>
                <a href="{{ route('staff.inventory.show', $batch->medicine) }}">{{ $batch->medicine->fullName() }}</a>
                <div class="small text-muted">{{ $batch->medicine->code }} · {{ $batch->medicine->unit }}</div>
              </td>
              <td>{{ $batch->batch_no }}</td>
              <td>
                {{ $batch->expiry_date->format('d/m/Y') }}
                @if ($batch->isExpired())
                  <span class="badge bg-danger">Hết hạn</span>
                @elseif ($batch->isNearExpiry())
                  <span class="badge bg-warning text-dark">Sắp hết hạn</span>
                @endif
              </td>
              <td class="text-end">{{ number_format($batch->quantity) }}</td>
              <td class="text-end">{{ number_format($batch->remaining) }}</td>
              <td class="text-end">{{ money($batch->import_price) }}</td>
              <td class="text-end">{{ money($batch->quantity * $batch->import_price) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <td colspan="7" class="text-end fw-bold">Tổng giá trị</td>
            <td class="text-end fw-bold text-primary">{{ money($receipt->total_amount) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <a href="{{ route('staff.receipts.index') }}" class="btn btn-link px-0 mt-2 no-print"><i class="bi bi-arrow-left me-1"></i>Quay lại danh sách</a>
@endsection
