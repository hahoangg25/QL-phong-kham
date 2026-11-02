{{--
  Bảng hàng đợi (render cả trang lẫn JSON polling).
  $doctor null -> tổng quan các bác sĩ hôm nay ($overview); có $doctor -> hàng đợi chi tiết ($board).
--}}
@if (! $doctor)
  <div class="row g-3">
    @forelse ($overview as $row)
      @php $d = $row['doctor']; @endphp
      <div class="col-md-6 col-xl-4">
        <a href="{{ route('staff.queue.index', ['doctor_id' => $d->id]) }}" class="card h-100 text-decoration-none text-body">
          <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
              <img src="{{ $d->avatarUrl() }}" class="rounded-circle" width="48" height="48" style="object-fit:cover" alt="">
              <div class="flex-grow-1">
                <div class="fw-semibold">{{ $d->display_name }}</div>
                <div class="small text-muted">{{ $d->specialty->name }} · Phòng {{ $d->room?->code ?? '-' }}</div>
              </div>
            </div>
            <div class="row text-center g-1">
              <div class="col-3"><div class="fs-4 fw-bold text-warning">{{ $row['waiting'] }}</div><div class="small text-muted">Chờ</div></div>
              <div class="col-3"><div class="fs-4 fw-bold text-primary">{{ $row['in_progress'] }}</div><div class="small text-muted">Đang khám</div></div>
              <div class="col-3"><div class="fs-4 fw-bold text-purple">{{ $row['awaiting'] }}</div><div class="small text-muted">Chờ CLS</div></div>
              <div class="col-3"><div class="fs-4 fw-bold text-success">{{ $row['done'] }}</div><div class="small text-muted">Xong</div></div>
            </div>
            <div class="small mt-3 pt-2 border-top">
              @if ($row['current'])
                <i class="bi bi-megaphone text-primary me-1"></i>Đang khám: <strong>STT {{ $row['current']->queue_number }}</strong> - {{ $row['current']->patient->full_name }}
              @else
                <span class="text-muted"><i class="bi bi-pause-circle me-1"></i>Chưa gọi bệnh nhân</span>
              @endif
            </div>
          </div>
        </a>
      </div>
    @empty
      <div class="col-12">
        <div class="card"><div class="empty-state"><i class="bi bi-calendar-x"></i>Hôm nay chưa có bác sĩ làm việc hoặc chưa có bệnh nhân.</div></div>
      </div>
    @endforelse
  </div>
@else
  @php
    $isLate = fn ($v) => $v->appointment && $v->checked_in_at && $v->checked_in_at->gt($v->appointment->scheduled_at->copy()->addMinutes($lateMinutes));
  @endphp
  <div class="row g-4">
    <div class="col-xl-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-hourglass-split text-warning me-1"></i>Đang chờ khám ({{ $board['waiting']->count() }})</span>
          <span class="small text-muted fw-normal">Ưu tiên trước, sau đó theo giờ hẹn/giờ đến</span>
        </div>
        <div class="card-body">
          @forelse ($board['waiting'] as $i => $v)
            <div class="queue-item {{ $v->is_priority ? 'priority' : '' }}">
              <div class="queue-no">{{ $v->queue_number }}</div>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold">
                  <span class="text-muted small me-1">#{{ $i + 1 }}</span>{{ $v->patient->full_name }}
                  @if ($v->is_priority)<span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i> Ưu tiên</span>@endif
                  @if ($isLate($v))<span class="badge bg-danger ms-1" title="Check-in trễ quá {{ $lateMinutes }} phút (BR-06)">Trễ</span>@endif
                </div>
                <div class="small text-muted">
                  {{ $v->patient->summary() }}
                  · @if ($v->type === 'walk_in')<span class="badge bg-secondary">Vãng lai</span>@else<span class="badge bg-info text-dark">Hẹn {{ $v->appointment?->scheduled_at->format('H:i') }}</span>@endif
                  · đến {{ $v->checked_in_at?->format('H:i') }} ({{ $v->checked_in_at?->diffForHumans(null, true) }})
                </div>
                @if ($v->reason)<div class="small text-truncate">{{ $v->reason }}</div>@endif
              </div>
              @can('reception.manage')
                <div class="dropdown">
                  <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"><i class="bi bi-three-dots-vertical"></i></button>
                  <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 260px">
                    <form method="POST" action="{{ route('staff.queue.priority', $v) }}">@csrf
                      <button class="dropdown-item rounded"><i class="bi bi-star me-2"></i>{{ $v->is_priority ? 'Bỏ ưu tiên' : 'Ưu tiên (người già, trẻ em)' }}</button>
                    </form>
                    <form method="POST" action="{{ route('staff.queue.move-to-end', $v) }}">@csrf
                      <button class="dropdown-item rounded"><i class="bi bi-arrow-down-circle me-2"></i>Đẩy xuống cuối hàng</button>
                    </form>
                    <a href="{{ route('staff.queue.ticket', $v) }}" target="_blank" class="dropdown-item rounded"><i class="bi bi-printer me-2"></i>In lại phiếu</a>
                    <hr class="my-2">
                    <form method="POST" action="{{ route('staff.queue.transfer', $v) }}" class="px-2">@csrf
                      <label class="small text-muted mb-1">Chuyển sang bác sĩ</label>
                      <div class="input-group input-group-sm">
                        <select name="doctor_id" class="form-select" required>
                          <option value="">-- Chọn --</option>
                          @foreach ($transferTargets as $t)
                            <option value="{{ $t->id }}">{{ $t->display_name }} ({{ $t->specialty->name }})</option>
                          @endforeach
                        </select>
                        <button class="btn btn-primary"><i class="bi bi-arrow-left-right"></i></button>
                      </div>
                    </form>
                    <hr class="my-2">
                    <form method="POST" action="{{ route('staff.queue.cancel', $v) }}" data-confirm="Hủy lượt chờ STT {{ $v->queue_number }} - {{ $v->patient->full_name }}?">@csrf
                      <button class="dropdown-item rounded text-danger"><i class="bi bi-x-circle me-2"></i>Hủy lượt chờ (bệnh nhân bỏ về)</button>
                    </form>
                  </div>
                </div>
              @endcan
            </div>
          @empty
            <div class="empty-state"><i class="bi bi-cup-hot"></i>Không có bệnh nhân đang chờ.</div>
          @endforelse
        </div>
      </div>
    </div>

    <div class="col-xl-6">
      <div class="card mb-4">
        <div class="card-header"><i class="bi bi-megaphone text-primary me-1"></i>Đang khám ({{ $board['in_progress']->count() }})</div>
        <div class="card-body">
          @forelse ($board['in_progress'] as $v)
            <div class="queue-item current">
              <div class="queue-no">{{ $v->queue_number }}</div>
              <div class="flex-grow-1">
                <div class="fw-semibold">{{ $v->patient->full_name }}</div>
                <div class="small text-muted">{{ $v->patient->summary() }} · gọi lúc {{ $v->called_at?->format('H:i') }}</div>
              </div>
            </div>
          @empty
            <div class="text-muted small">Bác sĩ chưa gọi bệnh nhân.</div>
          @endforelse
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><i class="bi bi-droplet-half text-purple me-1"></i>Chờ kết quả cận lâm sàng ({{ $board['awaiting_results']->count() }})</div>
        <div class="card-body">
          @forelse ($board['awaiting_results'] as $v)
            <div class="queue-item">
              <div class="queue-no">{{ $v->queue_number }}</div>
              <div class="flex-grow-1">
                <div class="fw-semibold">{{ $v->patient->full_name }}</div>
                <div class="small text-muted">{{ $v->patient->summary() }}</div>
              </div>
            </div>
          @empty
            <div class="text-muted small">Không có.</div>
          @endforelse
        </div>
      </div>

      <div class="card">
        <div class="card-header"><i class="bi bi-check2-circle text-success me-1"></i>Đã khám xong ({{ $board['completed']->count() }})</div>
        <div class="card-body">
          @forelse ($board['completed']->take(10) as $v)
            <div class="d-flex justify-content-between small py-1 border-bottom">
              <span><strong>{{ $v->queue_number }}</strong> - {{ $v->patient->full_name }}</span>
              <span class="text-muted">{{ $v->completed_at?->format('H:i') }}</span>
            </div>
          @empty
            <div class="text-muted small">Chưa có.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
@endif
