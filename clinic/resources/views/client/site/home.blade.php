@extends('client.layouts.site')

@section('title', 'Trang chủ')
@section('body_class', 'index-page')


@section('content')
  {{-- Hero --}}
  <section id="hero" class="hero section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <div class="hero-content">
            <div class="trust-badges mb-4" data-aos="fade-right" data-aos-delay="200">
              <div class="badge-item"><i class="bi bi-shield-check"></i><span>Bảo mật hồ sơ y tế</span></div>
              <div class="badge-item"><i class="bi bi-clock"></i><span>Đặt lịch 24/7</span></div>
              @if ($stats['reviews'])
                <div class="badge-item"><i class="bi bi-star-fill"></i><span>{{ number_format($stats['rating'], 1) }}/5 đánh giá</span></div>
              @endif
            </div>

            <h1 data-aos="fade-right" data-aos-delay="300">
              Kiến tạo nụ cười <span class="highlight">tự tin tỏa sáng</span>, đặt lịch khám chỉ trong 1 phút
            </h1>

            <p class="hero-description" data-aos="fade-right" data-aos-delay="400">
              Chọn chuyên khoa nha khoa, bác sĩ răng hàm mặt và khung giờ phù hợp ngay trên điện thoại. Không phải xếp hàng chờ đợi,
              nhận nhắc lịch tự động và tra cứu hồ sơ điều trị, phim X-Quang, đơn thuốc mọi lúc.
            </p>

            <div class="hero-stats mb-4" data-aos="fade-right" data-aos-delay="500">
              <div class="stat-item">
                <h3><span data-purecounter-start="0" data-purecounter-end="{{ $stats['doctors'] }}" data-purecounter-duration="2" class="purecounter">{{ $stats['doctors'] }}</span></h3>
                <p>Bác sĩ chuyên khoa</p>
              </div>
              <div class="stat-item">
                <h3><span data-purecounter-start="0" data-purecounter-end="{{ $stats['visits'] }}" data-purecounter-duration="2" class="purecounter">{{ $stats['visits'] }}</span>+</h3>
                <p>Lượt khám hoàn thành</p>
              </div>
              <div class="stat-item">
                <h3><span data-purecounter-start="0" data-purecounter-end="{{ $stats['specialties'] }}" data-purecounter-duration="2" class="purecounter">{{ $stats['specialties'] }}</span></h3>
                <p>Chuyên khoa</p>
              </div>
            </div>

            <div class="hero-actions" data-aos="fade-right" data-aos-delay="600">
              <a href="{{ route('account.booking.create') }}" class="btn btn-primary"><i class="bi bi-calendar-plus me-2"></i>Đặt lịch khám</a>
              <a href="{{ route('doctors.index') }}" class="btn btn-outline"><i class="bi bi-search me-2"></i>Tìm bác sĩ</a>
            </div>

            <div class="emergency-contact" data-aos="fade-right" data-aos-delay="700">
              <div class="emergency-icon"><i class="bi bi-telephone-fill"></i></div>
              <div class="emergency-info">
                <small>Hotline đặt lịch & tư vấn</small>
                <strong>{{ config('clinic.hotline') }}</strong>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="hero-visual" data-aos="fade-left" data-aos-delay="400">
            <div class="main-image">
              <img src="{{ asset('assets/img/health/staff-10.webp') }}" alt="{{ config('clinic.name') }}" class="img-fluid">
              <div class="floating-card appointment-card">
                <div class="card-icon"><i class="bi bi-calendar-check"></i></div>
                <div class="card-content">
                  <h6>Lịch trống gần nhất</h6>
                  @if ($nextSlot)
                    <p>{{ $nextSlot->starts_at->isToday() ? 'Hôm nay' : ($nextSlot->starts_at->isTomorrow() ? 'Ngày mai' : $nextSlot->starts_at->format('d/m')) }} {{ $nextSlot->starts_at->format('H:i') }}</p>
                    <small>{{ $nextSlot->doctor->display_name }}</small>
                  @else
                    <p>Liên hệ hotline</p>
                    <small>{{ config('clinic.hotline') }}</small>
                  @endif
                </div>
              </div>
              <div class="floating-card rating-card">
                <div class="card-content">
                  <div class="rating-stars">
                    @for ($i = 1; $i <= 5; $i++)<i class="bi bi-star-fill"></i>@endfor
                  </div>
                  <h6>{{ $stats['reviews'] ? number_format($stats['rating'], 1).'/5' : 'Mới' }}</h6>
                  <small>{{ number_format($stats['reviews'], 0, ',', '.') }} đánh giá</small>
                </div>
              </div>
            </div>
            <div class="background-elements">
              <div class="element element-1"></div>
              <div class="element element-2"></div>
              <div class="element element-3"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- Chuyên khoa nổi bật --}}
  <section id="featured-departments" class="featured-departments section">
    <div class="container section-title" data-aos="fade-up">
      <h2>Chuyên khoa nổi bật</h2>
      <p>Đội ngũ bác sĩ giàu kinh nghiệm, trang thiết bị hiện đại cho từng chuyên khoa</p>
    </div>

    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row g-5">
        @foreach ($specialties as $i => $specialty)
          <div class="col-lg-6" data-aos="zoom-in" data-aos-delay="{{ 100 + ($i % 2) * 100 }}">
            <div class="specialty-card">
              <div class="specialty-content">
                <div class="specialty-meta">
                  <span class="specialty-label">{{ $specialty->doctors_count }} bác sĩ</span>
                </div>
                <h3>{{ $specialty->name }}</h3>
                <p>{{ \Illuminate\Support\Str::limit($specialty->short_description, 160) }}</p>
                <div class="specialty-features">
                  <span><i class="bi bi-check-circle-fill"></i>Đặt lịch trực tuyến</span>
                  <span><i class="bi bi-check-circle-fill"></i>Nhắc lịch tự động</span>
                </div>
                <a href="{{ route('specialties.show', $specialty) }}" class="specialty-link">
                  Xem chuyên khoa <i class="bi bi-arrow-right"></i>
                </a>
              </div>
              <div class="specialty-visual">
                <img src="{{ $specialty->imageUrl() }}" alt="{{ $specialty->name }}" class="img-fluid" loading="lazy">
                <div class="visual-overlay">
                  <i class="fa-solid {{ $specialty->icon ?: 'fa-stethoscope' }}"></i>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
      <div class="text-center mt-5">
        <a href="{{ route('specialties.index') }}" class="btn btn-outline-primary px-4">Xem tất cả chuyên khoa <i class="bi bi-arrow-right ms-1"></i></a>
      </div>
    </div>
  </section>

  {{-- Quy trình đặt lịch --}}
  <section id="booking-steps" class="section light-background">
    <div class="container section-title" data-aos="fade-up">
      <h2>Quy trình đặt lịch 4 bước</h2>
      <p>Chủ động thời gian, giảm chờ đợi tại phòng khám</p>
    </div>
    <div class="container">
      <div class="row g-4">
        @foreach ([
            ['bi-hospital', 'Chọn chuyên khoa & bác sĩ', 'Tra cứu theo chuyên khoa, hoặc để hệ thống tự phân bác sĩ còn lịch trống.'],
            ['bi-calendar2-week', 'Chọn ngày & khung giờ', 'Xem khung giờ còn chỗ theo thời gian thực, chọn giờ phù hợp với bạn.'],
            ['bi-shield-lock', 'Xác nhận bằng OTP', 'Nhập mã OTP gửi qua email để giữ chỗ. Nhận ngay mã lịch hẹn và mã QR.'],
            ['bi-qr-code-scan', 'Đến khám & check-in', 'Đưa mã QR tại quầy lễ tân, nhận số thứ tự và theo dõi hàng đợi trên điện thoại.'],
        ] as $i => [$icon, $title, $desc])
          <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="{{ 100 + $i * 100 }}">
            <div class="card card-soft h-100 p-4 text-center">
              <div class="position-relative d-inline-block mx-auto mb-3">
                <div class="icon-circle mx-auto" style="width:64px;height:64px;font-size:28px"><i class="bi {{ $icon }}"></i></div>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">{{ $i + 1 }}</span>
              </div>
              <h5 class="mb-2">{{ $title }}</h5>
              <p class="text-muted small mb-0">{{ $desc }}</p>
            </div>
          </div>
        @endforeach
      </div>
      <div class="text-center mt-4">
        <a href="{{ route('account.booking.create') }}" class="btn btn-primary px-4 py-2"><i class="bi bi-calendar-plus me-2"></i>Đặt lịch ngay</a>
      </div>
    </div>
  </section>

  {{-- Tìm bác sĩ --}}
  <section id="find-a-doctor" class="find-a-doctor section">
    <div class="container section-title" data-aos="fade-up">
      <h2>Tìm bác sĩ</h2>
      <p>Các bác sĩ được bệnh nhân đánh giá cao nhất</p>
    </div>

    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row justify-content-center mb-5" data-aos="fade-up" data-aos-delay="200">
        <div class="col-lg-8 text-center">
          <div class="search-section">
            <h3 class="search-title">Tìm bác sĩ phù hợp với bạn</h3>
            <p class="search-subtitle">Tìm theo tên bác sĩ hoặc chuyên khoa</p>
            <form class="search-form" action="{{ route('doctors.index') }}" method="GET">
              <div class="search-input-group">
                <div class="input-wrapper">
                  <i class="bi bi-person"></i>
                  <input type="text" class="form-control" name="q" placeholder="Nhập tên bác sĩ">
                </div>
                <div class="select-wrapper">
                  <i class="bi bi-heart-pulse"></i>
                  <select class="form-select" name="specialty_id">
                    <option value="">Tất cả chuyên khoa</option>
                    @foreach ($specialtyOptions as $s)
                      <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                  </select>
                </div>
                <button type="submit" class="search-btn"><i class="bi bi-search"></i> Tìm bác sĩ</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="doctors-grid" data-aos="fade-up" data-aos-delay="300">
        @foreach ($doctors as $i => $doctor)
          <div class="doctor-profile" data-aos="zoom-in" data-aos-delay="{{ 100 + ($i % 3) * 100 }}">
            <div class="profile-header">
              <div class="doctor-avatar">
                <img src="{{ $doctor->avatarUrl() }}" alt="{{ $doctor->display_name }}" class="img-fluid">
                <div class="status-indicator available"></div>
              </div>
              <div class="doctor-details">
                <h4>{{ $doctor->display_name }}</h4>
                <span class="specialty-tag">{{ $doctor->specialty?->name }}</span>
                <div class="experience-info">
                  <i class="bi bi-award"></i>
                  <span>{{ $doctor->experience_years }} năm kinh nghiệm · {{ money($doctor->consultation_fee) }}</span>
                </div>
              </div>
            </div>
            <div class="rating-section">
              <div class="stars">
                @for ($s = 1; $s <= 5; $s++)
                  <i class="bi {{ $doctor->rating_avg >= $s ? 'bi-star-fill' : ($doctor->rating_avg >= $s - 0.5 ? 'bi-star-half' : 'bi-star') }}"></i>
                @endfor
              </div>
              <span class="rating-score">{{ number_format($doctor->rating_avg, 1) }}</span>
              <span class="review-count">({{ $doctor->rating_count }} đánh giá)</span>
            </div>
            <div class="action-buttons">
              <a href="{{ route('doctors.show', $doctor) }}" class="btn-secondary">Xem chi tiết</a>
              <a href="{{ route('account.booking.create', ['doctor_id' => $doctor->id]) }}" class="btn-primary">Đặt lịch</a>
            </div>
          </div>
        @endforeach
      </div>

      <div class="text-center mt-5">
        <a href="{{ route('doctors.index') }}" class="btn btn-outline-primary px-4">Xem tất cả bác sĩ <i class="bi bi-arrow-right ms-1"></i></a>
      </div>
    </div>
  </section>

  {{-- Kêu gọi hành động --}}
  <section id="call-to-action" class="call-to-action section light-background">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="hero-content">
        <div class="row align-items-center">
          <div class="col-lg-6">
            <div class="content-wrapper" data-aos="fade-up" data-aos-delay="200">
              <h1>Hồ sơ sức khỏe điện tử cho cả gia đình</h1>
              <p>Một tài khoản quản lý hồ sơ của bạn và người thân. Xem lại chẩn đoán, đơn thuốc, kết quả xét nghiệm
                của từng lần khám, thanh toán online và nhận thông báo nhắc lịch trước 24 giờ và 2 giờ.</p>
              <div class="cta-wrapper">
                <a href="{{ route('account.booking.create') }}" class="primary-cta">
                  <span>Đặt lịch khám</span><i class="bi bi-arrow-right"></i>
                </a>
                <a href="{{ route('services.index') }}" class="secondary-cta">
                  <span>Xem bảng giá</span><i class="bi bi-arrow-right"></i>
                </a>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="image-container" data-aos="fade-left" data-aos-delay="300">
              <img src="{{ asset('assets/img/health/facilities-9.webp') }}" alt="Cơ sở vật chất phòng khám" class="img-fluid">
            </div>
          </div>
        </div>
      </div>

      <div class="features-section">
        <div class="row g-0">
          <div class="col-lg-4">
            <div class="feature-block" data-aos="fade-up" data-aos-delay="200">
              <div class="feature-icon"><i class="bi bi-lightning-charge"></i></div>
              <h3>Không chờ đợi</h3>
              <p>Phân bổ bệnh nhân theo khung giờ, hàng đợi hiển thị theo thời gian thực tại sảnh và trên điện thoại.</p>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="feature-block" data-aos="fade-up" data-aos-delay="300">
              <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
              <h3>Bảo mật dữ liệu</h3>
              <p>Dữ liệu sức khỏe được mã hóa, phân quyền chặt chẽ và ghi nhật ký mọi lần truy cập.</p>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="feature-block" data-aos="fade-up" data-aos-delay="400">
              <div class="feature-icon"><i class="bi bi-people"></i></div>
              <h3>Bác sĩ giàu kinh nghiệm</h3>
              <p>{{ $stats['doctors'] }} bác sĩ thuộc {{ $stats['specialties'] }} chuyên khoa, tư vấn tận tình và chu đáo.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="contact-block">
        <div class="row">
          <div class="col-lg-8">
            <div class="contact-content" data-aos="fade-up" data-aos-delay="200">
              <h2>Cần tư vấn hoặc đặt lịch qua điện thoại?</h2>
              <p>Lễ tân sẵn sàng hỗ trợ đặt lịch hộ, đổi lịch và giải đáp thắc mắc trong giờ làm việc ({{ config('clinic.working_hours') }}).</p>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="contact-actions" data-aos="fade-up" data-aos-delay="300">
              <a href="tel:{{ preg_replace('/\D/', '', config('clinic.hotline')) }}" class="emergency-call">
                <i class="bi bi-telephone"></i><span>{{ config('clinic.hotline') }}</span>
              </a>
              <a href="{{ route('specialties.index') }}" class="contact-link">Xem chuyên khoa</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
