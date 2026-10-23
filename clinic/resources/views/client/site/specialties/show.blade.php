@extends('client.layouts.site')

@section('title', $specialty->name)
@section('page_title', $specialty->name)
@section('page_subtitle', $specialty->short_description)
@section('breadcrumbs')
  <li><a href="{{ route('specialties.index') }}">Chuyên khoa</a></li>
@endsection

@section('content')
  <section class="section pt-4">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="card card-soft overflow-hidden mb-4">
            <img src="{{ $specialty->imageUrl() }}" alt="{{ $specialty->name }}" class="w-100" style="max-height:340px;object-fit:cover">
            <div class="card-body p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="icon-circle"><i class="fa-solid {{ $specialty->icon ?: 'fa-stethoscope' }}"></i></div>
                <h2 class="h4 mb-0">Giới thiệu chuyên khoa {{ $specialty->name }}</h2>
              </div>
              <div class="text-secondary">{!! nl2br(e($specialty->description ?: $specialty->short_description)) !!}</div>
              @if ($specialty->keywordList())
                <div class="mt-3">
                  <div class="small fw-semibold mb-2">Thường khám khi có các biểu hiện:</div>
                  @foreach (array_slice($specialty->keywordList(), 0, 12) as $kw)
                    <span class="badge rounded-pill bg-light text-secondary border me-1 mb-1">{{ $kw }}</span>
                  @endforeach
                </div>
              @endif
            </div>
          </div>

          <h3 class="h5 mb-3"><i class="bi bi-person-badge me-2 text-primary"></i>Bác sĩ chuyên khoa ({{ $doctors->count() }})</h3>
          <div class="row g-4 mb-4">
            @forelse ($doctors as $doctor)
              <div class="col-md-6 col-xl-4">
                @include('client.site.partials.doctor-card', ['doctor' => $doctor])
              </div>
            @empty
              <div class="col-12 text-muted">Chuyên khoa hiện chưa có bác sĩ nhận lịch.</div>
            @endforelse
          </div>

          @if ($services->isNotEmpty())
            <h3 class="h5 mb-3"><i class="bi bi-tags me-2 text-primary"></i>Dịch vụ & bảng giá</h3>
            <div class="card card-soft">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr><th>Dịch vụ</th><th>Loại</th><th class="text-end">Giá</th></tr>
                  </thead>
                  <tbody>
                    @foreach ($services as $service)
                      <tr>
                        <td>{{ $service->name }}<div class="small text-muted">{{ $service->code }}</div></td>
                        <td><span class="small"><i class="bi {{ $service->type->icon() }} me-1 text-primary"></i>{{ $service->type->label() }}</span></td>
                        <td class="text-end fw-semibold">{{ money($service->price) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          @endif
        </div>

        <aside class="col-lg-4">
          <div class="card card-soft mb-4" style="position:sticky;top:110px">
            <div class="card-body p-4 text-center">
              <div class="icon-circle mx-auto mb-3" style="width:64px;height:64px;font-size:28px"><i class="bi bi-calendar-plus"></i></div>
              <h5>Đặt lịch khám {{ $specialty->name }}</h5>
              <p class="text-muted small">Chọn bác sĩ và khung giờ còn trống, xác nhận bằng mã OTP.</p>
              <a href="{{ route('account.booking.create', ['specialty_id' => $specialty->id]) }}" class="btn btn-primary w-100 mb-2">
                <i class="bi bi-calendar-plus me-1"></i>Đặt lịch ngay
              </a>
              <a href="tel:{{ preg_replace('/\D/', '', config('clinic.hotline')) }}" class="btn btn-outline-primary w-100">
                <i class="bi bi-telephone me-1"></i>{{ config('clinic.hotline') }}
              </a>
            </div>
            @if ($others->isNotEmpty())
              <div class="card-footer bg-white p-0">
                <div class="px-4 pt-3 pb-2 small fw-semibold text-muted text-uppercase">Chuyên khoa khác</div>
                <div class="list-group list-group-flush">
                  @foreach ($others as $other)
                    <a href="{{ route('specialties.show', $other) }}" class="list-group-item list-group-item-action border-0 px-4">
                      <i class="fa-solid {{ $other->icon ?: 'fa-stethoscope' }} me-2 text-primary" style="width:18px"></i>{{ $other->name }}
                    </a>
                  @endforeach
                </div>
              </div>
            @endif
          </div>
        </aside>
      </div>
    </div>
  </section>
@endsection
