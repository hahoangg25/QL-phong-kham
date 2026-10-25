@extends('client.layouts.account')

@section('title', 'Lịch sử khám')
@section('page_title', 'Lịch sử khám bệnh')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
@endsection

@section('account_title', 'Lịch sử khám')
@section('account_actions')
  <form method="GET">
    <select name="patient_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">Tất cả hồ sơ</option>
      @foreach ($patients as $p)
        <option value="{{ $p->id }}" @selected($patientId === $p->id)>{{ $p->full_name }} ({{ $p->relationship }})</option>
      @endforeach
    </select>
  </form>
@endsection

@section('account_content')
  @forelse ($visits as $v)
    <div class="card card-soft mb-3">
      <div class="card-body d-flex flex-wrap gap-3 align-items-start">
        <div class="text-center rounded-3 px-2 py-2" style="background:#eaf3fc;min-width:78px">
          <div class="fw-bold fs-5 text-primary lh-1">{{ $v->visit_date->format('d/m') }}</div>
          <div class="small text-muted">{{ $v->visit_date->format('Y') }}</div>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="fw-semibold">{{ $v->specialty->name }} · {{ $v->doctor->display_name }}</span>
            {!! status_badge($v->status) !!}
          </div>
          <div class="small text-muted mb-1"><i class="bi bi-person me-1"></i>{{ $v->patient->full_name }} · Mã lượt khám {{ $v->code }}</div>
          @if ($v->diagnoses->isNotEmpty())
            <div class="small"><strong>Chẩn đoán:</strong>
              {{ $v->diagnoses->map(fn ($d) => $d->icd10_code.' - '.$d->name)->implode('; ') }}
            </div>
          @elseif ($v->reason)
            <div class="small"><strong>Lý do khám:</strong> {{ \Illuminate\Support\Str::limit($v->reason, 100) }}</div>
          @endif
          <div class="small mt-1">
            @if ($v->prescription)<span class="badge bg-light text-dark border me-1"><i class="bi bi-capsule me-1"></i>Có đơn thuốc</span>@endif
            @if ($v->status->value === 'completed' && ! $v->review)<span class="badge bg-warning-subtle text-warning-emphasis border"><i class="bi bi-star me-1"></i>Chưa đánh giá</span>@endif
            @if ($v->review)<span class="badge bg-light text-dark border"><i class="bi bi-star-fill text-warning me-1"></i>{{ $v->review->rating }}/5</span>@endif
          </div>
        </div>
        <a href="{{ route('account.visits.show', $v) }}" class="btn btn-outline-primary btn-sm align-self-center">Xem chi tiết</a>
      </div>
    </div>
  @empty
    <div class="card card-soft">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-journal-medical fs-1 d-block mb-2"></i>Chưa có lượt khám nào.
      </div>
    </div>
  @endforelse

  {{ $visits->links() }}
@endsection
