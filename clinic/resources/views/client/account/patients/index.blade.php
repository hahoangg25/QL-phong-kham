@extends('client.layouts.account')

@section('title', 'Hồ sơ người thân')
@section('page_title', 'Hồ sơ bệnh nhân')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
@endsection

@section('account_title', 'Hồ sơ bệnh nhân')
@section('account_actions')
  <a href="{{ route('account.patients.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Thêm hồ sơ</a>
@endsection

@section('account_content')
  <p class="text-muted small">Một tài khoản có thể quản lý nhiều hồ sơ (bản thân, bố mẹ, con...). Mỗi hồ sơ có mã bệnh nhân và lịch sử khám riêng.
    Thông tin dị ứng và tiền sử bệnh được mã hóa khi lưu trữ.</p>

  <div class="row g-3">
    @foreach ($patients as $p)
      <div class="col-md-6">
        <div class="card card-soft h-100">
          <div class="card-body">
            <div class="d-flex align-items-start gap-3">
              <div class="icon-circle"><i class="bi {{ $p->gender?->value === 'female' ? 'bi-person-standing-dress' : 'bi-person-standing' }}"></i></div>
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <div class="fw-semibold fs-6">{{ $p->full_name }}</div>
                    <span class="badge bg-primary-subtle text-primary">{{ $p->relationship }}</span>
                    <span class="small text-muted ms-1">Mã {{ $p->code }}</span>
                  </div>
                </div>
                <div class="small text-muted mt-2">
                  {{ $p->genderLabel() }}{{ $p->date_of_birth ? ' · '.$p->date_of_birth->format('d/m/Y').' ('.$p->age.' tuổi)' : '' }}
                  @if ($p->phone)<br><i class="bi bi-telephone me-1"></i>{{ $p->phone }}@endif
                  @if ($p->blood_type)<span class="ms-2"><i class="bi bi-droplet me-1"></i>Nhóm máu {{ $p->blood_type }}</span>@endif
                </div>
                @if ($p->allergies)
                  <div class="small mt-1 text-danger"><i class="bi bi-exclamation-diamond me-1"></i>Dị ứng: {{ $p->allergies }}</div>
                @endif
                <div class="small mt-1">
                  <span class="me-2"><i class="bi bi-calendar2-week me-1"></i>{{ $p->active_appointments_count }} lịch đang hoạt động</span>
                  <span><i class="bi bi-journal-medical me-1"></i>{{ $p->visits_count }} lượt khám</span>
                </div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
            <a href="{{ route('account.booking.create', ['patient_id' => $p->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-calendar-plus me-1"></i>Đặt lịch</a>
            <a href="{{ route('account.visits.index', ['patient_id' => $p->id]) }}" class="btn btn-sm btn-outline-secondary">Lịch sử khám</a>
            <a href="{{ route('account.patients.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
            @if ($p->relationship !== 'Bản thân')
              <form method="POST" action="{{ route('account.patients.destroy', $p) }}" data-confirm="Xóa hồ sơ {{ $p->full_name }}?">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endsection
