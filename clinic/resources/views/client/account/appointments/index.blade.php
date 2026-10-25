@extends('client.layouts.account')

@section('title', 'Lịch hẹn của tôi')
@section('page_title', 'Lịch hẹn của tôi')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
@endsection

@section('account_title', 'Lịch hẹn khám')
@section('account_actions')
  <a href="{{ route('account.booking.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-calendar-plus me-1"></i>Đặt lịch mới</a>
@endsection

@section('account_content')
  <div class="card card-soft">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
      <ul class="nav nav-pills small">
        @foreach (['sap-toi' => 'Sắp tới', 'da-qua' => 'Đã qua', 'tat-ca' => 'Tất cả'] as $key => $label)
          <li class="nav-item">
            <a class="nav-link py-1 {{ $tab === $key ? 'active' : '' }}" href="{{ route('account.appointments.index', array_filter(['tab' => $key, 'patient_id' => $patientId])) }}">{{ $label }}</a>
          </li>
        @endforeach
      </ul>
      <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <select name="patient_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">Tất cả hồ sơ</option>
          @foreach ($patients as $p)
            <option value="{{ $p->id }}" @selected($patientId === $p->id)>{{ $p->full_name }} ({{ $p->relationship }})</option>
          @endforeach
        </select>
      </form>
    </div>
    <div class="card-body p-0">
      @forelse ($appointments as $a)
        <a href="{{ route('account.appointments.show', $a) }}" class="d-flex flex-wrap align-items-center gap-3 px-3 py-3 border-bottom text-decoration-none text-body appointment-row">
          <div class="text-center rounded-3 px-2 py-1" style="background:#eaf3fc;min-width:70px">
            <div class="small text-muted">{{ $a->scheduled_at->translatedFormat('D') }}</div>
            <div class="fw-bold fs-5 text-primary lh-1">{{ $a->scheduled_at->format('d/m') }}</div>
            <div class="small fw-semibold">{{ $a->scheduled_at->format('H:i') }}</div>
          </div>
          <div class="flex-grow-1">
            <div class="fw-semibold">{{ $a->specialty->name }} · {{ $a->doctor->display_name }}</div>
            <div class="small text-muted">
              <i class="bi bi-person me-1"></i>{{ $a->patient->full_name }}
              <span class="mx-1">·</span><i class="bi bi-upc me-1"></i>{{ $a->code }}
              @if ($a->symptoms)<span class="mx-1">·</span>{{ \Illuminate\Support\Str::limit($a->symptoms, 50) }}@endif
            </div>
          </div>
          <div>{!! status_badge($a->status) !!}</div>
          <i class="bi bi-chevron-right text-muted"></i>
        </a>
      @empty
        <div class="text-center text-muted py-5">
          <i class="bi bi-calendar2-x fs-1 d-block mb-2"></i>
          Chưa có lịch hẹn nào.
          <div class="mt-3"><a href="{{ route('account.booking.create') }}" class="btn btn-outline-primary btn-sm">Đặt lịch khám ngay</a></div>
        </div>
      @endforelse
    </div>
    @if ($appointments->hasPages())
      <div class="card-footer bg-transparent">{{ $appointments->links() }}</div>
    @endif
  </div>
@endsection

@push('styles')
  <style>.appointment-row:hover { background: #f8fbff; }</style>
@endpush
