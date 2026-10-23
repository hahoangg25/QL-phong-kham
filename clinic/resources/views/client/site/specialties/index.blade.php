@extends('client.layouts.site')

@section('title', 'Chuyên khoa')
@section('page_title', 'Chuyên khoa')
@section('page_subtitle', 'Danh sách các chuyên khoa đang hoạt động tại phòng khám. Chọn chuyên khoa để xem bác sĩ, dịch vụ và đặt lịch.')

@section('content')
  <section id="departments" class="departments section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      @if ($specialties->isEmpty())
        <div class="text-center text-muted py-5">Chưa có chuyên khoa nào.</div>
      @endif

      <div class="row g-5">
        @foreach ($specialties as $i => $specialty)
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="{{ 100 + ($i % 3) * 100 }}">
            <div class="department-card">
              <div class="department-icon">
                <i class="fa-solid {{ $specialty->icon ?: 'fa-stethoscope' }}"></i>
              </div>
              <div class="department-image">
                <img src="{{ $specialty->imageUrl() }}" alt="{{ $specialty->name }}" class="img-fluid" loading="lazy">
              </div>
              <div class="department-content">
                <h3>{{ $specialty->name }}</h3>
                <p>{{ \Illuminate\Support\Str::limit($specialty->short_description, 150) }}</p>
                <p class="small text-muted mb-3"><i class="bi bi-person-badge me-1"></i>{{ $specialty->doctors_count }} bác sĩ</p>
                <a href="{{ route('specialties.show', $specialty) }}" class="learn-more">
                  <span>Xem chi tiết</span>
                  <i class="fas fa-arrow-right"></i>
                </a>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>
@endsection
