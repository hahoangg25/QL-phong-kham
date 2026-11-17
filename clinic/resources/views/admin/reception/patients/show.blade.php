@extends('admin.layouts.app')

@section('title', $patient->full_name)
@section('page_title', 'Hồ sơ bệnh nhân')
@section('breadcrumbs')
  <a href="{{ route('staff.patients.index') }}">Bệnh nhân</a> / {{ $patient->code }}
@endsection

@section('content')
  <div class="patient-banner d-flex flex-wrap align-items-center gap-3 mb-4">
    <div class="flex-grow-1">
      <h2 class="h4 mb-1">{{ $patient->full_name }}
        @if ($patient->isPriority())<span class="badge bg-warning text-dark fs-6 align-middle ms-1">Ưu tiên</span>@endif
      </h2>
      <div class="opacity-75">{{ $patient->code }} · {{ $patient->summary() }}{{ $patient->blood_type ? ' · Nhóm máu '.$patient->blood_type : '' }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      @can('patients.manage')
        <a href="{{ route('staff.patients.edit', $patient) }}" class="btn btn-light btn-sm"><i class="bi bi-pencil me-1"></i>Sửa thông tin</a>
      @endcan
      @can('appointments.manage')
        <a href="{{ route('staff.appointments.create', ['patient_id' => $patient->id]) }}" class="btn btn-light btn-sm"><i class="bi bi-calendar-plus me-1"></i>Đặt lịch</a>
      @endcan
      @can('reception.manage')
        <a href="{{ route('staff.reception.walkin', ['patient_id' => $patient->id]) }}" class="btn btn-warning btn-sm"><i class="bi bi-ticket-perforated me-1"></i>Tiếp nhận khám</a>
      @endcan
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header">Thông tin hành chính</div>
        <div class="card-body small">
          <dl class="row mb-0">
            <dt class="col-5 text-muted fw-normal">Điện thoại</dt><dd class="col-7">{{ $patient->phone ?: '-' }}</dd>
            <dt class="col-5 text-muted fw-normal">Email</dt><dd class="col-7 text-break">{{ $patient->email ?: '-' }}</dd>
            <dt class="col-5 text-muted fw-normal">CCCD</dt><dd class="col-7">{{ $patient->id_number ?: '-' }}</dd>
            <dt class="col-5 text-muted fw-normal">Số BHYT</dt><dd class="col-7">{{ $patient->insurance_number ?: '-' }}</dd>
            <dt class="col-5 text-muted fw-normal">Nghề nghiệp</dt><dd class="col-7">{{ $patient->occupation ?: '-' }}</dd>
            <dt class="col-5 text-muted fw-normal">Địa chỉ</dt><dd class="col-7">{{ $patient->address ?: '-' }}</dd>
            <dt class="col-5 text-muted fw-normal">Liên hệ khẩn cấp</dt><dd class="col-7">{{ $patient->emergency_contact ?: '-' }}</dd>
            <dt class="col-5 text-muted fw-normal">Ngày tạo</dt><dd class="col-7">{{ $patient->created_at->format('d/m/Y') }}</dd>
          </dl>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Tiền sử y tế</div>
        <div class="card-body small">
          <div class="mb-2"><strong>Dị ứng:</strong>
            @if ($patient->allergies)
              <div class="allergy-alert mt-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ $patient->allergies }}</div>
            @else
              <span class="text-muted">Chưa ghi nhận</span>
            @endif
          </div>
          <div><strong>Bệnh nền / tiền sử:</strong> <span class="{{ $patient->medical_history ? '' : 'text-muted' }}">{{ $patient->medical_history ?: 'Chưa ghi nhận' }}</span></div>
          <div class="text-muted mt-2" style="font-size: 12px"><i class="bi bi-shield-lock me-1"></i>Dữ liệu nhạy cảm được mã hóa; mọi lượt xem hồ sơ được ghi nhật ký (BR-08, BR-11).</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">Tài khoản quản lý</div>
        <div class="card-body small">
          @if ($patient->user)
            <div class="fw-semibold">{{ $patient->user->name }}</div>
            <div class="text-muted">{{ $patient->user->email }} · {{ $patient->user->phone }}</div>
            <div class="mt-1">Quan hệ: {{ $patient->relationship }}</div>
            <div class="mt-1">Vắng mặt:
              <span class="badge {{ $patient->user->exceedsNoShowThreshold() ? 'bg-danger' : 'bg-secondary' }}">{{ $patient->user->no_show_count }}/{{ setting('no_show_threshold') }}</span>
              @unless ($patient->user->is_active)<span class="badge bg-dark ms-1">Tài khoản bị khóa</span>@endunless
            </div>
          @else
            <span class="text-muted">Bệnh nhân vãng lai, chưa liên kết tài khoản trực tuyến.</span>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header">Lượt khám gần đây ({{ $patient->visits->count() }})</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Ngày</th><th>Bác sĩ / Chuyên khoa</th><th>Chẩn đoán</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
              @forelse ($patient->visits as $v)
                <tr>
                  <td class="text-nowrap">{{ $v->visit_date->format('d/m/Y') }}<div class="small text-muted">{{ $v->code }}</div></td>
                  <td><div class="small">{{ $v->doctor->display_name }}</div><div class="small text-muted">{{ $v->specialty->name }}</div></td>
                  <td class="small">
                    @forelse ($v->diagnoses as $dx)
                      <div>{{ $dx->icd10_code }} - {{ $dx->name }}{{ $dx->is_primary ? '' : ' (kèm)' }}</div>
                    @empty
                      <span class="text-muted">-</span>
                    @endforelse
                  </td>
                  <td>{!! status_badge($v->status) !!}</td>
                  <td class="text-end">
                    @can('medical.view')
                      <a href="{{ route('staff.records.show', $v) }}" class="btn btn-sm btn-outline-secondary" title="Xem bệnh án"><i class="bi bi-journal-medical"></i></a>
                    @endcan
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-muted">Chưa có lượt khám.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">Lịch hẹn ({{ $patient->appointments->count() }} gần nhất)</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Thời gian</th><th>Mã lịch</th><th>Bác sĩ / Chuyên khoa</th><th>Trạng thái</th></tr></thead>
            <tbody>
              @forelse ($patient->appointments as $a)
                <tr>
                  <td class="text-nowrap">{{ $a->scheduled_at->format('H:i d/m/Y') }}</td>
                  <td>
                    @can('appointments.view')
                      <a href="{{ route('staff.appointments.show', $a) }}">{{ $a->code }}</a>
                    @else
                      {{ $a->code }}
                    @endcan
                  </td>
                  <td><div class="small">{{ $a->doctor->display_name }}</div><div class="small text-muted">{{ $a->specialty->name }}</div></td>
                  <td>{!! status_badge($a->status) !!}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-muted">Chưa có lịch hẹn.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
