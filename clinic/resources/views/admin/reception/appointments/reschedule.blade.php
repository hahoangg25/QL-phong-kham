@extends('admin.layouts.app')

@section('title', 'Đổi lịch '.$appointment->code)
@section('page_title', 'Đổi lịch hẹn '.$appointment->code)
@section('breadcrumbs')
  <a href="{{ route('staff.appointments.index') }}">Lịch hẹn</a> / <a href="{{ route('staff.appointments.show', $appointment) }}">{{ $appointment->code }}</a> / Đổi lịch
@endsection

@section('content')
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">Lịch hiện tại</div>
        <div class="card-body">
          <div class="fw-semibold">{{ $appointment->patient->full_name }}</div>
          <div class="small text-muted mb-3">{{ $appointment->patient->code }} · {{ $appointment->patient->phone }}</div>
          <div><i class="bi bi-clock me-1"></i>{{ $appointment->scheduled_at->format('H:i d/m/Y') }}</div>
          <div><i class="bi bi-person-badge me-1"></i>{{ $appointment->doctor->display_name }}</div>
          <div><i class="bi bi-hospital me-1"></i>{{ $appointment->specialty->name }}</div>
          <div class="mt-2">{!! status_badge($appointment->status) !!}</div>
          <hr>
          <div class="small text-muted">Lịch cũ sẽ chuyển sang "Đã đổi lịch" (hoặc "Đã hủy" nếu đang chờ xác nhận), slot cũ được giải phóng và hệ thống tạo lịch mới đã xác nhận. Tiền đã trả trước được chuyển sang lịch mới.</div>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <form method="POST" action="{{ route('staff.appointments.reschedule.store', $appointment) }}" class="card" id="rescheduleForm">
        @csrf
        <div class="card-header">Chọn khung giờ mới - chuyên khoa {{ $appointment->specialty->name }}</div>
        <div class="card-body">
          @include('admin.reception.appointments._slot_picker', [
              'doctors' => $doctors,
              'specialties' => null,
              'selectedDoctorId' => $appointment->doctor_id,
              'excludeSlotId' => $appointment->time_slot_id,
          ])
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
          <a href="{{ route('staff.appointments.show', $appointment) }}" class="btn btn-outline-secondary">Quay lại</a>
          <button class="btn btn-primary" id="submitBtn"><i class="bi bi-arrow-repeat me-1"></i>Xác nhận đổi lịch</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.getElementById('rescheduleForm').addEventListener('submit', (e) => {
      if (!document.getElementById('timeSlotId').value) {
        e.preventDefault();
        alert('Vui lòng chọn khung giờ mới.');
        setTimeout(() => { document.getElementById('submitBtn').disabled = false; }, 10);
      }
    });
  </script>
@endpush
