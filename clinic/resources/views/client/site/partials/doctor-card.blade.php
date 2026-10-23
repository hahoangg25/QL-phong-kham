{{-- Thẻ bác sĩ dùng chung. Tham số: $doctor (đã eager-load user, specialty) --}}
<div class="card doctor-card border-0 shadow-sm">
  <a href="{{ route('doctors.show', $doctor) }}" class="photo d-block">
    <img src="{{ $doctor->avatarUrl() }}" alt="{{ $doctor->display_name }}" loading="lazy">
  </a>
  <div class="card-body d-flex flex-column">
    <span class="badge bg-light text-primary align-self-start mb-2">{{ $doctor->specialty?->name }}</span>
    <h5 class="card-title mb-1">
      <a href="{{ route('doctors.show', $doctor) }}" class="text-reset">{{ $doctor->display_name }}</a>
    </h5>
    @if ($doctor->position)
      <div class="small text-muted mb-2">{{ $doctor->position }}</div>
    @endif
    <div class="small mb-2">
      @include('client.site.partials.stars', ['rating' => $doctor->rating_avg])
      <span class="text-muted ms-1">{{ number_format($doctor->rating_avg, 1) }} ({{ $doctor->rating_count }} đánh giá)</span>
    </div>
    <ul class="list-unstyled small text-muted mb-3">
      <li><i class="bi bi-award me-1 text-primary"></i>{{ $doctor->experience_years }} năm kinh nghiệm</li>
      <li><i class="bi bi-cash-coin me-1 text-primary"></i>Phí khám: <strong class="text-dark">{{ money($doctor->consultation_fee) }}</strong></li>
    </ul>
    <div class="d-flex gap-2 mt-auto">
      <a href="{{ route('doctors.show', $doctor) }}" class="btn btn-outline-primary btn-sm flex-fill">Xem chi tiết</a>
      <a href="{{ route('account.booking.create', ['doctor_id' => $doctor->id]) }}" class="btn btn-primary btn-sm flex-fill">
        <i class="bi bi-calendar-plus me-1"></i>Đặt lịch
      </a>
    </div>
  </div>
</div>
