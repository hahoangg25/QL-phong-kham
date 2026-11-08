{{-- Bảng hàng đợi của bác sĩ (được làm mới định kỳ qua ?partial=1) --}}
<div class="row g-3 mb-3">
  @foreach ([
      ['waiting', 'Đang chờ', 'bi-hourglass-split', 'soft-warning'],
      ['in_progress', 'Đang khám', 'bi-clipboard2-pulse', 'soft-primary'],
      ['awaiting_results', 'Chờ kết quả CLS', 'bi-droplet-half', 'soft-purple'],
      ['completed', 'Đã khám xong', 'bi-check2-circle', 'soft-success'],
  ] as [$key, $label, $icon, $color])
    <div class="col-6 col-xl-3">
      <div class="card stat-card">
        <div class="stat-icon {{ $color }}"><i class="bi {{ $icon }}"></i></div>
        <div>
          <div class="stat-value">{{ $board[$key]->count() }}</div>
          <div class="stat-label">{{ $label }}</div>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="row g-3">
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ol me-1"></i>Danh sách chờ khám</span>
        <small class="text-muted fw-normal">Ưu tiên người cao tuổi, trẻ nhỏ; trễ hẹn xuống cuối (BR-06)</small>
      </div>
      <div class="card-body">
        @forelse ($board['waiting'] as $i => $v)
          <div class="queue-item {{ $v->is_priority ? 'priority' : '' }}">
            <div class="queue-no">{{ $v->queue_number }}</div>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold">
                {{ $v->patient->full_name }}
                @if ($v->is_priority)<span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i> Ưu tiên</span>@endif
                @if ($i === 0)<span class="badge bg-primary ms-1">Tiếp theo</span>@endif
              </div>
              <div class="small text-muted text-truncate">
                {{ $v->patient->genderLabel() }}{{ $v->patient->age !== null ? ' · '.$v->patient->age.' tuổi' : '' }}
                · {{ $v->type === 'walk_in' ? 'Vãng lai' : 'Hẹn '.optional($v->appointment?->scheduled_at)->format('H:i') }}
                · Check-in {{ $v->checked_in_at?->format('H:i') }}
                @if ($v->reason) · {{ \Illuminate\Support\Str::limit($v->reason, 60) }} @endif
              </div>
            </div>
            <form method="POST" action="{{ route('staff.exam.call', $v) }}">
              @csrf
              <button class="btn btn-sm btn-outline-primary"><i class="bi bi-megaphone me-1"></i>Gọi</button>
            </form>
          </div>
        @empty
          <div class="empty-state"><i class="bi bi-cup-hot"></i>Chưa có bệnh nhân chờ khám.</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-clipboard2-pulse me-1"></i>Đang khám</div>
      <div class="card-body">
        @forelse ($board['in_progress'] as $v)
          <div class="queue-item current">
            <div class="queue-no">{{ $v->queue_number }}</div>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold">{{ $v->patient->full_name }}</div>
              <div class="small text-muted">Gọi lúc {{ $v->called_at?->format('H:i') }}</div>
            </div>
            <div class="d-flex gap-1">
              <a href="{{ route('staff.exam.show', $v) }}" class="btn btn-sm btn-primary" title="Mở bệnh án"><i class="bi bi-journal-medical"></i></a>
              <form method="POST" action="{{ route('staff.exam.recall', $v) }}">@csrf
                <button class="btn btn-sm btn-outline-secondary" title="Gọi lại"><i class="bi bi-megaphone"></i></button>
              </form>
              @if (! $v->clinical_notes)
                <form method="POST" action="{{ route('staff.exam.skip', $v) }}" data-confirm="Bệnh nhân không có mặt? Chuyển xuống cuối hàng đợi.">@csrf
                  <button class="btn btn-sm btn-outline-danger" title="Bỏ qua (không có mặt)"><i class="bi bi-skip-forward"></i></button>
                </form>
              @endif
            </div>
          </div>
        @empty
          <div class="text-muted small">Chưa gọi bệnh nhân nào.</div>
        @endforelse
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-droplet-half me-1"></i>Chờ kết quả cận lâm sàng</div>
      <div class="card-body">
        @forelse ($board['awaiting_results'] as $v)
          <a href="{{ route('staff.exam.show', [$v, 'tab' => 'orders']) }}" class="queue-item text-decoration-none text-body">
            <div class="queue-no">{{ $v->queue_number }}</div>
            <div class="flex-grow-1">
              <div class="fw-semibold">{{ $v->patient->full_name }}</div>
              <div class="small text-muted">Bấm để xem kết quả và kết luận</div>
            </div>
            {!! status_badge($v->status) !!}
          </a>
        @empty
          <div class="text-muted small">Không có.</div>
        @endforelse
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-check2-circle me-1"></i>Đã khám xong hôm nay</div>
      <div class="list-group list-group-flush">
        @forelse ($board['completed'] as $v)
          <a href="{{ route('staff.exam.show', $v) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
            <span><strong class="me-2">#{{ $v->queue_number }}</strong>{{ $v->patient->full_name }}</span>
            <small class="text-muted">{{ $v->completed_at?->format('H:i') }}</small>
          </a>
        @empty
          <div class="list-group-item text-muted small">Chưa có.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>
