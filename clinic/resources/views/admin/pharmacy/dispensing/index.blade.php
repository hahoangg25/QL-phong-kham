@extends('admin.layouts.app')

@section('title', 'Cấp phát thuốc')
@section('page_title', 'Cấp phát thuốc')
@section('breadcrumbs', 'Dược / Đơn thuốc')

@section('content')
  <ul class="nav nav-tabs mb-3">
    @foreach (['ready' => ['Chờ cấp thuốc', 'bg-warning text-dark'], 'unpaid' => ['Chưa thanh toán', 'bg-secondary'], 'dispensed' => ['Đã cấp hôm nay', 'bg-success']] as $key => [$label, $badge])
      <li class="nav-item">
        <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('staff.pharmacy.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}">
          {{ $label }} <span class="badge {{ $badge }} ms-1">{{ $counts[$key] }}</span>
        </a>
      </li>
    @endforeach
  </ul>

  <form class="filter-bar row g-2 align-items-end" method="GET">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="col-md-6">
      <label class="form-label">Tìm kiếm</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Mã đơn thuốc, tên, SĐT hoặc mã bệnh nhân">
    </div>
    <div class="col-md-6 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Tìm</button>
      <a href="{{ route('staff.pharmacy.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary">Xóa lọc</a>
      <a href="{{ route('staff.inventory.index') }}" class="btn btn-outline-primary ms-auto"><i class="bi bi-boxes me-1"></i>Tồn kho</a>
    </div>
  </form>

  @if ($tab === 'unpaid')
    <div class="alert alert-info py-2"><i class="bi bi-info-circle me-1"></i>Các đơn dưới đây chưa được thanh toán tại quầy thu ngân nên chưa thể cấp phát (TD-05).</div>
  @endif

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Mã đơn</th>
            <th>Bệnh nhân</th>
            <th>Bác sĩ kê đơn</th>
            <th class="text-center">Số thuốc</th>
            <th>Hóa đơn</th>
            <th>Trạng thái</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($prescriptions as $p)
            <tr>
              <td>
                <a href="{{ route('staff.pharmacy.show', $p) }}" class="fw-semibold">{{ $p->code }}</a>
                <div class="small text-muted">{{ $p->created_at->format('H:i d/m/Y') }}</div>
              </td>
              <td>
                {{ $p->patient?->full_name }}
                <div class="small text-muted">{{ $p->patient?->code }} · {{ $p->patient?->phone }}</div>
              </td>
              <td>{{ $p->doctor?->display_name }}</td>
              <td class="text-center">{{ $p->items->count() }}</td>
              <td>
                @if ($p->visit?->invoice)
                  {!! status_badge($p->visit->invoice->status) !!}
                  <div class="small text-muted">{{ $p->visit->invoice->code }}</div>
                @else
                  <span class="text-muted">Chưa lập</span>
                @endif
              </td>
              <td>
                {!! status_badge($p->status) !!}
                @if ($p->dispensed_at)
                  <div class="small text-muted">{{ $p->dispensed_at->format('H:i') }} · {{ $p->dispenser?->name }}</div>
                @endif
              </td>
              <td class="text-end">
                <a href="{{ route('staff.pharmacy.show', $p) }}" class="btn btn-sm {{ $tab === 'ready' ? 'btn-primary' : 'btn-outline-secondary' }}">
                  @if ($tab === 'ready')<i class="bi bi-capsule me-1"></i>Cấp thuốc @else Chi tiết @endif
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-capsule"></i>Không có đơn thuốc nào.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($prescriptions->hasPages())
      <div class="card-footer">{{ $prescriptions->links() }}</div>
    @endif
  </div>
@endsection
