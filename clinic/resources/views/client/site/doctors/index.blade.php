@extends('client.layouts.site')

@section('title', 'Đội ngũ bác sĩ')
@section('page_title', 'Đội ngũ bác sĩ')
@section('page_subtitle', 'Tra cứu hồ sơ, chuyên môn, phí khám và lịch làm việc của bác sĩ')

@section('content')
  <section class="section pt-4 doctors-list">
    <div class="container">
      <form method="GET" action="{{ route('doctors.index') }}" class="card card-soft mb-4">
        <div class="card-body">
          <div class="row g-2 align-items-end">
            <div class="col-md-5">
              <label class="form-label small text-muted mb-1">Tên bác sĩ</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Nhập tên bác sĩ">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted mb-1">Chuyên khoa</label>
              <select name="specialty_id" class="form-select">
                <option value="">Tất cả chuyên khoa</option>
                @foreach ($specialties as $s)
                  <option value="{{ $s->id }}" @selected($specialtyId === $s->id)>{{ $s->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
              <button class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Lọc</button>
              @if ($q !== '' || $specialtyId)
                <a href="{{ route('doctors.index') }}" class="btn btn-outline-secondary">Xóa lọc</a>
              @endif
            </div>
          </div>
        </div>
      </form>

      <p class="text-muted small mb-3">Tìm thấy <strong>{{ $doctors->total() }}</strong> bác sĩ</p>

      <div class="row g-4">
        @forelse ($doctors as $doctor)
          <div class="col-sm-6 col-lg-4 col-xl-3" data-aos="fade-up">
            @include('client.site.partials.doctor-card', ['doctor' => $doctor])
          </div>
        @empty
          <div class="col-12">
            <div class="text-center text-muted py-5">
              <i class="bi bi-person-x fs-1 d-block mb-2"></i>
              Không tìm thấy bác sĩ phù hợp. Hãy thử từ khóa khác.
            </div>
          </div>
        @endforelse
      </div>

      <div class="mt-4 d-flex justify-content-center">
        {{ $doctors->links() }}
      </div>
    </div>
  </section>
@endsection
