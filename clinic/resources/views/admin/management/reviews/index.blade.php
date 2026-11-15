@extends('admin.layouts.app')

@section('title', 'Đánh giá')
@section('page_title', 'Quản lý đánh giá bác sĩ')
@section('breadcrumbs', 'Quản trị / Đánh giá')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="stat-icon soft-warning"><i class="bi bi-star-fill"></i></div>
        <div><div class="stat-value">{{ number_format($stats['avg'], 2) }}</div><div class="stat-label">Điểm trung bình</div></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="stat-icon soft-primary"><i class="bi bi-chat-square-text"></i></div>
        <div><div class="stat-value">{{ number_format($stats['count']) }}</div><div class="stat-label">Đánh giá hiển thị</div></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="stat-icon soft-danger"><i class="bi bi-eye-slash"></i></div>
        <div><div class="stat-value">{{ number_format($stats['hidden']) }}</div><div class="stat-label">Đã ẩn</div></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 h-100">
        @foreach ($stats['distribution'] as $star => $count)
          @php $pct = $stats['count'] ? round($count * 100 / $stats['count']) : 0; @endphp
          <div class="d-flex align-items-center gap-2 small">
            <span style="width:28px">{{ $star }}<i class="bi bi-star-fill text-warning"></i></span>
            <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar bg-warning" style="width:{{ $pct }}%"></div></div>
            <span class="text-muted" style="width:32px">{{ $count }}</span>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-3">
      <label class="form-label">Bác sĩ</label>
      <select name="doctor_id" class="form-select">
        <option value="">Tất cả</option>
        @foreach ($doctors as $d)
          <option value="{{ $d->id }}" @selected(request('doctor_id') == $d->id)>{{ $d->display_name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Số sao</label>
      <select name="rating" class="form-select">
        <option value="">Tất cả</option>
        @for ($i = 5; $i >= 1; $i--)
          <option value="{{ $i }}" @selected(request('rating') == $i)>{{ $i }} sao</option>
        @endfor
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Hiển thị</label>
      <select name="hidden" class="form-select">
        <option value="">Tất cả</option>
        <option value="0" @selected(request('hidden') === '0')>Đang hiển thị</option>
        <option value="1" @selected(request('hidden') === '1')>Đã ẩn</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Nội dung</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Từ khóa trong nhận xét">
    </div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-search me-1"></i>Lọc</button></div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Thời gian</th>
            <th>Bác sĩ</th>
            <th>Người đánh giá</th>
            <th>Đánh giá</th>
            <th>Trạng thái</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($reviews as $review)
            <tr class="{{ $review->is_hidden ? 'text-muted' : '' }}">
              <td class="small text-nowrap">{{ $review->created_at->format('H:i d/m/Y') }}</td>
              <td>{{ $review->doctor?->display_name }}</td>
              <td class="small">{{ $review->user?->name }}<br><span class="text-muted">BN: {{ $review->patient?->full_name }}</span></td>
              <td style="max-width:380px">
                <div class="rating-stars">
                  @for ($i = 1; $i <= 5; $i++)
                    <i class="bi {{ $i <= $review->rating ? 'bi-star-fill text-warning' : 'bi-star text-secondary' }}"></i>
                  @endfor
                </div>
                <div class="small">{{ $review->comment ?: '(Không có nhận xét)' }}</div>
              </td>
              <td>
                @if ($review->is_hidden)
                  <span class="badge bg-secondary">Đã ẩn</span>
                @else
                  <span class="badge bg-success-subtle text-success-emphasis">Hiển thị</span>
                @endif
              </td>
              <td class="text-end">
                <form method="POST" action="{{ route('staff.admin.reviews.toggle', $review) }}" data-confirm="{{ $review->is_hidden ? 'Hiển thị lại đánh giá này?' : 'Ẩn đánh giá này khỏi trang bác sĩ?' }}">
                  @csrf
                  <button class="btn btn-sm {{ $review->is_hidden ? 'btn-outline-success' : 'btn-outline-danger' }}">
                    <i class="bi {{ $review->is_hidden ? 'bi-eye' : 'bi-eye-slash' }}"></i> {{ $review->is_hidden ? 'Hiện' : 'Ẩn' }}
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="empty-state"><i class="bi bi-star"></i>Chưa có đánh giá phù hợp.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($reviews->hasPages())
      <div class="card-footer">{{ $reviews->links() }}</div>
    @endif
  </div>
@endsection
