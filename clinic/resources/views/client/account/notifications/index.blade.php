@extends('client.layouts.account')

@section('title', 'Thông báo')
@section('page_title', 'Thông báo')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
@endsection

@section('account_title', 'Thông báo')
@section('account_actions')
  @if (auth()->user()->unreadNotifications()->exists())
    <form method="POST" action="{{ route('account.notifications.read-all') }}">
      @csrf
      <button class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all me-1"></i>Đánh dấu tất cả đã đọc</button>
    </form>
  @endif
@endsection

@section('account_content')
  <div class="card card-soft">
    <div class="list-group list-group-flush">
      @forelse ($notifications as $n)
        <form method="POST" action="{{ route('account.notifications.read', $n->id) }}" class="list-group-item list-group-item-action p-0 {{ $n->read_at ? '' : 'bg-light' }}">
          @csrf
          <button class="btn w-100 text-start d-flex gap-3 px-3 py-3 border-0 bg-transparent">
            <span class="icon-circle" style="width:40px;height:40px;font-size:18px"><i class="bi {{ $n->data['icon'] ?? 'bi-bell' }}"></i></span>
            <span class="flex-grow-1">
              <span class="d-flex justify-content-between gap-2">
                <span class="fw-semibold">{{ $n->data['title'] ?? 'Thông báo' }}
                  @unless ($n->read_at)<span class="badge bg-danger ms-1">Mới</span>@endunless
                </span>
                <span class="small text-muted text-nowrap">{{ $n->created_at->diffForHumans() }}</span>
              </span>
              <span class="d-block small text-muted">{{ $n->data['message'] ?? '' }}</span>
            </span>
          </button>
        </form>
      @empty
        <div class="text-center text-muted py-5"><i class="bi bi-bell-slash fs-1 d-block mb-2"></i>Chưa có thông báo nào.</div>
      @endforelse
    </div>
    @if ($notifications->hasPages())
      <div class="card-footer bg-transparent">{{ $notifications->links() }}</div>
    @endif
  </div>
@endsection
