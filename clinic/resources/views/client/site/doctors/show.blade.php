@extends('client.layouts.site')

@section('title', $doctor->display_name)
@section('page_title', $doctor->display_name)
@section('page_subtitle', ($doctor->position ? $doctor->position.' · ' : '').'Chuyên khoa '.$doctor->specialty->name)
@section('breadcrumbs')
  <li><a href="{{ route('doctors.index') }}">Bác sĩ</a></li>
@endsection

@section('content')
  <section class="section pt-4">
    <div class="container">
      <div class="row g-4">
        {{-- Hồ sơ --}}
        <aside class="col-lg-4">
          <div class="card card-soft overflow-hidden" style="position:sticky;top:110px">
            <div class="doctor-card">
              <div class="photo"><img src="{{ $doctor->avatarUrl() }}" alt="{{ $doctor->display_name }}"></div>
            </div>
            <div class="card-body p-4">
              <h2 class="h5 mb-1">{{ $doctor->display_name }}</h2>
              <a href="{{ route('specialties.show', $doctor->specialty) }}" class="badge bg-light text-primary text-decoration-none mb-2">{{ $doctor->specialty->name }}</a>
              <div class="mb-3">
                @include('client.site.partials.stars', ['rating' => $doctor->rating_avg])
                <span class="small text-muted ms-1">{{ number_format($doctor->rating_avg, 1) }}/5 · {{ $doctor->rating_count }} đánh giá</span>
              </div>
              <ul class="list-unstyled small mb-4">
                <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted"><i class="bi bi-award me-2"></i>Kinh nghiệm</span><strong>{{ $doctor->experience_years }} năm</strong></li>
                <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted"><i class="bi bi-cash-coin me-2"></i>Phí khám</span><strong class="text-primary">{{ money($doctor->consultation_fee) }}</strong></li>
                @if ($doctor->room)
                  <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted"><i class="bi bi-door-open me-2"></i>Phòng khám</span><strong>{{ $doctor->room->label() }}</strong></li>
                @endif
                @if ($doctor->position)
                  <li class="d-flex justify-content-between py-2"><span class="text-muted"><i class="bi bi-briefcase me-2"></i>Chức vụ</span><strong>{{ $doctor->position }}</strong></li>
                @endif
              </ul>
              <a href="{{ route('account.booking.create', ['doctor_id' => $doctor->id]) }}" class="btn btn-primary w-100 py-2">
                <i class="bi bi-calendar-plus me-2"></i>Đặt lịch với bác sĩ
              </a>
            </div>
          </div>
        </aside>

        <div class="col-lg-8">
          {{-- Lịch trống 7 ngày tới --}}
          <div class="card card-soft mb-4">
            <div class="card-header"><i class="bi bi-calendar2-check me-2 text-primary"></i>Lịch trống 7 ngày tới</div>
            <div class="card-body">
              <div class="date-strip">
                @foreach ($availability as $day)
                  @php $open = $day['bookable'] > 0; @endphp
                  <a href="{{ $open ? route('account.booking.create', ['doctor_id' => $doctor->id, 'date' => $day['date']->toDateString()]) : '#' }}"
                     class="date-chip text-decoration-none {{ $open ? '' : 'off' }}">
                    <div class="dow">{{ $day['date']->isToday() ? 'Hôm nay' : $day['date']->translatedFormat('D') }}</div>
                    <div class="day">{{ $day['date']->format('d/m') }}</div>
                    <div class="small {{ $open ? 'text-success' : 'text-muted' }}">
                      {{ $day['total'] ? ($open ? $day['bookable'].' khung giờ' : 'Hết chỗ') : 'Nghỉ' }}
                    </div>
                  </a>
                @endforeach
              </div>
              <p class="small text-muted mb-0 mt-2"><i class="bi bi-info-circle me-1"></i>Đặt lịch trước giờ khám ít nhất {{ setting('booking_min_hours_before') }} giờ. Bấm vào ngày để chọn khung giờ.</p>
            </div>
          </div>

          {{-- Giới thiệu --}}
          <div class="card card-soft mb-4">
            <div class="card-header"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Giới thiệu</div>
            <div class="card-body">
              @if ($doctor->short_bio)
                <p class="fw-semibold">{{ $doctor->short_bio }}</p>
              @endif
              <div class="text-secondary">{!! nl2br(e($doctor->bio ?: 'Đang cập nhật.')) !!}</div>
            </div>
          </div>

          {{-- Lịch làm việc tuần --}}
          <div class="card card-soft mb-4">
            <div class="card-header"><i class="bi bi-calendar-week me-2 text-primary"></i>Lịch làm việc hàng tuần</div>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <tbody>
                  @foreach ($schedule as $day)
                    <tr>
                      <th class="ps-4" style="width:140px">{{ $day['label'] }}</th>
                      <td>
                        @forelse ($day['shifts'] as $shift)
                          <span class="badge bg-light text-dark border me-1 py-2 px-3">
                            <i class="bi bi-clock me-1 text-primary"></i>{{ $shift->timeRange() }}
                            @if ($shift->room)<span class="text-muted">· {{ $shift->room->code }}</span>@endif
                          </span>
                        @empty
                          <span class="text-muted small">Không làm việc</span>
                        @endforelse
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>

          {{-- Đánh giá --}}
          <div class="card card-soft mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="bi bi-chat-square-heart me-2 text-primary"></i>Đánh giá của bệnh nhân</span>
              <span class="small text-muted">{{ $doctor->rating_count }} đánh giá</span>
            </div>
            <div class="card-body">
              @forelse ($reviews as $review)
                <div class="d-flex gap-3 {{ ! $loop->last ? 'border-bottom pb-3 mb-3' : '' }}">
                  <div class="icon-circle" style="width:40px;height:40px;font-size:16px"><i class="bi bi-person"></i></div>
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between flex-wrap">
                      <strong>{{ mask_name($review->patient?->full_name) }}</strong>
                      <span class="small text-muted">{{ $review->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="small">@include('client.site.partials.stars', ['rating' => $review->rating])</div>
                    @if ($review->comment)
                      <p class="mb-0 mt-1 text-secondary">{{ $review->comment }}</p>
                    @endif
                  </div>
                </div>
              @empty
                <p class="text-muted mb-0">Chưa có đánh giá nào.</p>
              @endforelse
            </div>
          </div>

          @if ($related->isNotEmpty())
            <h3 class="h5 mb-3">Bác sĩ cùng chuyên khoa</h3>
            <div class="row g-4">
              @foreach ($related as $other)
                <div class="col-md-4">@include('client.site.partials.doctor-card', ['doctor' => $other])</div>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>
  </section>
@endsection
