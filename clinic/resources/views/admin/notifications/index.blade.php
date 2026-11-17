@extends('admin.layouts.app')

@section('title', 'Thông báo')
@section('page_title', 'Thông báo')

@section('content')
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Thông báo của bạn</span>
      @if (auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('staff.notifications.read-all') }}">@csrf
          <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-all me-1"></i>Đánh dấu đã đọc tất cả</button>
        </form>
      @endif
    </div>
    <div class="list-group list-group-flush">
      @forelse ($notifications as $n)
        <form method="POST" action="{{ route('staff.notifications.read', $n->id) }}" class="list-group-item list-group-item-action {{ $n->read_at ? '' : 'bg-light' }}">
          @csrf
          <button class="btn p-0 text-start w-100 d-flex gap-3 align-items-start border-0">
            <span class="stat-icon soft-primary rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px">
              <i class="bi {{ $n->data['icon'] ?? 'bi-bell' }}"></i>
            </span>
            <span class="flex-grow-1">
              <span class="d-flex justify-content-between">
                <strong class="{{ $n->read_at ? 'fw-normal' : '' }}">{{ $n->data['title'] ?? 'Thông báo' }}</strong>
                <small class="text-muted text-nowrap ms-2">{{ $n->created_at->diffForHumans() }}</small>
              </span>
              <span class="d-block small text-muted">{{ $n->data['message'] ?? '' }}</span>
            </span>
            @unless ($n->read_at)<span class="badge bg-primary rounded-pill">Mới</span>@endunless
          </button>
        </form>
      @empty
        <div class="empty-state"><i class="bi bi-bell-slash"></i>Chưa có thông báo nào.</div>
      @endforelse
    </div>
    @if ($notifications->hasPages())
      <div class="card-footer">{{ $notifications->links() }}</div>
    @endif
  </div>
@endsection
