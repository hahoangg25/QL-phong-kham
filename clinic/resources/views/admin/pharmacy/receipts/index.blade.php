@extends('admin.layouts.app')

@section('title', 'Nhập kho')
@section('page_title', 'Phiếu nhập kho')
@section('breadcrumbs', 'Dược & kho / Nhập kho')

@section('content')
  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-4">
      <label class="form-label">Tìm kiếm</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Mã phiếu hoặc nhà cung cấp">
    </div>
    <div class="col-md-2">
      <label class="form-label">Từ ngày</label>
      <input type="date" name="from" value="{{ request('from') }}" class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label">Đến ngày</label>
      <input type="date" name="to" value="{{ request('to') }}" class="form-control">
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Lọc</button>
      <a href="{{ route('staff.receipts.create') }}" class="btn btn-success ms-auto"><i class="bi bi-plus-lg me-1"></i>Lập phiếu nhập</a>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Mã phiếu</th>
            <th>Ngày nhập</th>
            <th>Nhà cung cấp</th>
            <th class="text-center">Số lô</th>
            <th class="text-end">Tổng giá trị</th>
            <th>Người lập</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($receipts as $r)
            <tr>
              <td><a href="{{ route('staff.receipts.show', $r) }}" class="fw-semibold">{{ $r->code }}</a></td>
              <td>{{ $r->received_date->format('d/m/Y') }}</td>
              <td>{{ $r->supplier }}@if ($r->note)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($r->note, 60) }}</div>@endif</td>
              <td class="text-center">{{ $r->batches_count }}</td>
              <td class="text-end">{{ money($r->total_amount) }}</td>
              <td class="small">{{ $r->creator?->name }}</td>
              <td class="text-end"><a href="{{ route('staff.receipts.show', $r) }}" class="btn btn-sm btn-outline-secondary">Chi tiết</a></td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-box-arrow-in-down"></i>Chưa có phiếu nhập nào.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($receipts->hasPages())
      <div class="card-footer">{{ $receipts->links() }}</div>
    @endif
  </div>
@endsection
