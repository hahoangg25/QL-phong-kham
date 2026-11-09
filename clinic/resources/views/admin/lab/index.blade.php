@extends('admin.layouts.app')

@section('title', 'Cận lâm sàng')
@section('page_title', 'Chỉ định cận lâm sàng')

@section('content')
  <form method="GET" class="filter-bar row g-2 align-items-end">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="col-sm-6 col-md-3">
      <label class="form-label">Ngày chỉ định</label>
      <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control form-control-sm">
    </div>
    <div class="col-sm-6 col-md-3">
      <label class="form-label">Loại dịch vụ</label>
      <select name="type" class="form-select form-select-sm">
        <option value="">Tất cả</option>
        @foreach ($types as $t)
          <option value="{{ $t->value }}" @selected($type === $t)>{{ $t->label() }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Bệnh nhân</label>
      <input type="text" name="q" value="{{ $term }}" class="form-control form-control-sm" placeholder="Tên, SĐT, mã bệnh nhân">
    </div>
    <div class="col-md-2 d-flex gap-1">
      <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Lọc</button>
      <a href="{{ route('staff.lab.index') }}" class="btn btn-sm btn-outline-secondary" title="Bỏ lọc"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>

  <ul class="nav nav-tabs">
    @foreach ([
        'pending' => ['Chờ thực hiện', 'bi-hourglass-split'],
        'in_progress' => ['Đang thực hiện', 'bi-gear'],
        'completed' => ['Có kết quả', 'bi-check2-circle'],
    ] as $key => [$label, $icon])
      <li class="nav-item">
        <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('staff.lab.index', array_filter(['tab' => $key, 'date' => $date->toDateString(), 'type' => $type?->value, 'q' => $term])) }}">
          <i class="bi {{ $icon }} me-1"></i>{{ $label }} <span class="badge rounded-pill bg-secondary-subtle text-secondary">{{ $counts[$key] }}</span>
        </a>
      </li>
    @endforeach
  </ul>

  <div class="card rounded-top-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr><th>Chỉ định lúc</th><th>Bệnh nhân</th><th>Dịch vụ</th><th>Bác sĩ chỉ định</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr>
        </thead>
        <tbody>
          @forelse ($orders as $o)
            @php $p = $o->visit->patient; @endphp
            <tr>
              <td class="text-nowrap">{{ $o->created_at->format('H:i') }}<div class="small text-muted">{{ $o->created_at->format('d/m/Y') }}</div></td>
              <td>
                <div class="fw-semibold">{{ $p->full_name }}</div>
                <div class="small text-muted">{{ $p->code }} · {{ $p->genderLabel() }}{{ $p->age !== null ? ' · '.$p->age.' tuổi' : '' }}</div>
              </td>
              <td>
                {{ $o->service->name }}
                <div class="small text-muted">{{ $o->service->type->label() }}@if ($o->note) · {{ \Illuminate\Support\Str::limit($o->note, 40) }}@endif</div>
              </td>
              <td class="small">{{ $o->visit->doctor->display_name }}<div class="text-muted">Phòng {{ $o->visit->room?->code ?? '—' }}</div></td>
              <td>
                {!! status_badge($o->status) !!}
                @if ($o->result)<div class="small text-muted">{{ $o->result->completed_at?->format('H:i') }}</div>@endif
              </td>
              <td class="text-end text-nowrap">
                @if ($o->status === \App\Enums\ServiceOrderStatus::Pending)
                  <form method="POST" action="{{ route('staff.lab.start', $o) }}" class="d-inline">@csrf
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-play-fill"></i> Bắt đầu</button>
                  </form>
                @endif
                <a href="{{ route('staff.lab.show', $o) }}" class="btn btn-sm {{ $o->status === \App\Enums\ServiceOrderStatus::InProgress ? 'btn-primary' : 'btn-outline-secondary' }}">
                  @if ($o->status === \App\Enums\ServiceOrderStatus::Completed)<i class="bi bi-eye"></i> Xem/sửa
                  @else<i class="bi bi-pencil-square"></i> Nhập kết quả @endif
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="empty-state"><i class="bi bi-droplet"></i>Không có chỉ định nào.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($orders->hasPages())
      <div class="card-footer">{{ $orders->links() }}</div>
    @endif
  </div>
@endsection

@if ($tab !== 'completed' && $date->isToday())
  @push('scripts')
    <script>
      // Tự tải lại danh sách chỉ định mới mỗi 30 giây khi không thao tác
      setInterval(() => { if (!document.hidden && !document.querySelector('input:focus, select:focus')) location.reload(); }, 30000);
    </script>
  @endpush
@endif
