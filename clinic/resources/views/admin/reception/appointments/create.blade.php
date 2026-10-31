@extends('admin.layouts.app')

@section('title', 'Đặt lịch hộ')
@section('page_title', 'Đặt lịch hộ bệnh nhân')
@section('breadcrumbs')
  <a href="{{ route('staff.appointments.index') }}">Lịch hẹn</a> / Đặt lịch hộ
@endsection

@section('content')
  <form method="POST" action="{{ route('staff.appointments.store') }}" id="bookingForm">
    @csrf
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card mb-4">
          <div class="card-header"><span class="badge bg-primary me-2">1</span>Bệnh nhân</div>
          <div class="card-body">
            @include('admin.reception.patients._picker', ['patient' => $patient, 'genders' => $genders, 'extended' => false])
          </div>
        </div>
        <div class="card">
          <div class="card-header"><span class="badge bg-primary me-2">3</span>Triệu chứng / lý do khám</div>
          <div class="card-body">
            <textarea name="symptoms" rows="3" class="form-control" placeholder="vd: Sốt 2 ngày, ho khan">{{ old('symptoms') }}</textarea>
            <div class="form-text">Lịch do lễ tân đặt được xác nhận ngay. Áp dụng BR-01 (sức chứa slot) và BR-03 (một lịch/chuyên khoa/ngày).</div>
          </div>
          <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('staff.appointments.index') }}" class="btn btn-outline-secondary">Hủy</a>
            <button class="btn btn-primary" id="submitBtn"><i class="bi bi-calendar-check me-1"></i>Đặt lịch</button>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header"><span class="badge bg-primary me-2">2</span>Chọn bác sĩ và khung giờ</div>
          <div class="card-body">
            @include('admin.reception.appointments._slot_picker', ['doctors' => $doctors, 'specialties' => $specialties, 'selectedDoctorId' => request('doctor_id')])
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection

@push('scripts')
  <script>
    document.getElementById('bookingForm').addEventListener('submit', (e) => {
      if (!document.getElementById('timeSlotId').value) {
        e.preventDefault();
        alert('Vui lòng chọn khung giờ khám.');
        setTimeout(() => { document.getElementById('submitBtn').disabled = false; }, 10);
      }
    });
  </script>
@endpush
