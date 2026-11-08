@extends('admin.layouts.app')

@section('title', 'Bệnh án '.$visit->code)
@section('page_title', 'Bệnh án '.$visit->code)
@section('breadcrumbs')
  <a href="{{ route('staff.patients.show', $visit->patient_id) }}">{{ $visit->patient->full_name }}</a> / Bệnh án (chỉ đọc)
@endsection

@php
  $patient = $visit->patient;
  $orders = $visit->serviceOrders->where('status', '!=', \App\Enums\ServiceOrderStatus::Cancelled);
@endphp

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      {!! status_badge($visit->status) !!}
      @if ($visit->isLocked())<span class="locked-badge ms-1"><i class="bi bi-lock-fill me-1"></i>Đã khóa {{ $visit->locked_at->format('H:i d/m/Y') }}</span>@endif
      <span class="text-muted small ms-2"><i class="bi bi-shield-check me-1"></i>Lượt xem này được ghi vào nhật ký truy cập hồ sơ y tế</span>
    </div>
    <div class="d-flex gap-2">
      @if ($visit->prescription && $visit->prescription->items->isNotEmpty())
        <a href="{{ route('staff.records.print-prescription', $visit) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>In đơn thuốc</a>
      @endif
      @if ($orders->isNotEmpty())
        <a href="{{ route('staff.records.print-orders', $visit) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>In phiếu chỉ định</a>
      @endif
      @if (auth()->user()->doctor?->id === $visit->doctor_id)
        <a href="{{ route('staff.exam.show', $visit) }}" class="btn btn-sm btn-primary"><i class="bi bi-journal-medical me-1"></i>Mở màn hình khám</a>
      @endif
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-person me-1"></i>Bệnh nhân</div>
        <div class="card-body small">
          <div class="fw-semibold fs-6">{{ $patient->full_name }}</div>
          <div class="text-muted mb-2">{{ $patient->code }}</div>
          <dl class="row mb-0">
            <dt class="col-5 fw-normal text-muted">Giới tính</dt><dd class="col-7">{{ $patient->genderLabel() ?: '—' }}</dd>
            <dt class="col-5 fw-normal text-muted">Ngày sinh</dt><dd class="col-7">{{ $patient->date_of_birth?->format('d/m/Y') ?? '—' }}{{ $patient->age !== null ? ' ('.$patient->age.' tuổi)' : '' }}</dd>
            <dt class="col-5 fw-normal text-muted">Điện thoại</dt><dd class="col-7">{{ $patient->phone ?? '—' }}</dd>
            <dt class="col-5 fw-normal text-muted">Địa chỉ</dt><dd class="col-7">{{ $patient->address ?? '—' }}</dd>
            <dt class="col-5 fw-normal text-muted">Nhóm máu</dt><dd class="col-7">{{ $patient->blood_type ?? '—' }}</dd>
          </dl>
          @if ($patient->allergies)<div class="allergy-alert mt-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>Dị ứng: {{ $patient->allergies }}</div>@endif
          @if ($patient->medical_history)<div class="mt-2"><strong>Tiền sử:</strong> {{ $patient->medical_history }}</div>@endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-info-circle me-1"></i>Lượt khám</div>
        <div class="card-body small">
          <dl class="row mb-0">
            <dt class="col-5 fw-normal text-muted">Ngày khám</dt><dd class="col-7">{{ $visit->visit_date->format('d/m/Y') }}</dd>
            <dt class="col-5 fw-normal text-muted">Bác sĩ</dt><dd class="col-7">{{ $visit->doctor->display_name }}</dd>
            <dt class="col-5 fw-normal text-muted">Chuyên khoa</dt><dd class="col-7">{{ $visit->specialty?->name }}</dd>
            <dt class="col-5 fw-normal text-muted">Phòng</dt><dd class="col-7">{{ $visit->room?->label() ?? '—' }}</dd>
            <dt class="col-5 fw-normal text-muted">Hình thức</dt><dd class="col-7">{{ $visit->type === 'walk_in' ? 'Vãng lai' : 'Có lịch hẹn '.($visit->appointment?->code ?? '') }}</dd>
            <dt class="col-5 fw-normal text-muted">Số thứ tự</dt><dd class="col-7">{{ $visit->queue_number }}</dd>
            <dt class="col-5 fw-normal text-muted">Thời gian</dt><dd class="col-7">{{ $visit->started_at?->format('H:i') ?? '—' }} - {{ $visit->completed_at?->format('H:i') ?? '…' }}</dd>
            @if ($visit->invoice)
              <dt class="col-5 fw-normal text-muted">Hóa đơn</dt><dd class="col-7">{{ $visit->invoice->code }} {!! status_badge($visit->invoice->status) !!}</dd>
            @endif
          </dl>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><i class="bi bi-clock-history me-1"></i>Các lượt khám khác</div>
        <div class="list-group list-group-flush">
          @forelse ($history as $h)
            <a href="{{ route('staff.records.show', $h) }}" class="list-group-item list-group-item-action small">
              <div class="d-flex justify-content-between"><strong>{{ $h->visit_date->format('d/m/Y') }}</strong>{!! status_badge($h->status) !!}</div>
              <div class="text-muted">{{ $h->doctor->display_name }}</div>
              @if ($h->diagnoses->isNotEmpty())<div>{{ $h->diagnoses->map(fn ($d) => $d->icd10_code)->implode(', ') }}</div>@endif
            </a>
          @empty
            <div class="list-group-item small text-muted">Không có.</div>
          @endforelse
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-heart-pulse me-1"></i>Khám lâm sàng</div>
        <div class="card-body">
          <p class="mb-1"><strong>Lý do khám:</strong> {{ $visit->reason ?? '—' }}</p>
          <p class="mb-1"><strong>Triệu chứng:</strong> {{ $visit->symptoms ?? '—' }}</p>
          <p class="mb-1"><strong>Sinh hiệu:</strong> {{ $visit->vitalsSummary() ?: '—' }}{{ $visit->bmi() ? ' · BMI '.$visit->bmi() : '' }}</p>
          @if ($visit->clinical_notes)<div class="mt-2" style="white-space: pre-line">{{ $visit->clinical_notes }}</div>@endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-journal-medical me-1"></i>Chẩn đoán bệnh lý</div>
        <div class="card-body">
          @forelse ($visit->diagnoses as $d)
            <div class="mb-1">
              <span class="badge {{ $d->is_primary ? 'bg-primary' : 'bg-secondary' }} me-1">{{ $d->is_primary ? 'Chính' : 'Kèm theo' }}</span>
              <strong>{{ $d->icd10_code }}</strong> - {{ $d->name }}
              @if ($d->note)<span class="text-muted small">({{ $d->note }})</span>@endif
            </div>
          @empty
            <span class="text-muted">Chưa có chẩn đoán.</span>
          @endforelse
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-droplet-half me-1"></i>Cận lâm sàng</div>
        <div class="card-body">
          @forelse ($visit->serviceOrders as $o)
            <div class="border rounded p-2 mb-2">
              <div class="d-flex justify-content-between">
                <strong>{{ $o->service->name }}</strong>
                {!! status_badge($o->status) !!}
              </div>
              @if ($o->result)
                <div class="small mt-1" style="white-space: pre-line">{{ $o->result->result }}</div>
                @if ($o->result->conclusion)<div class="small"><strong>Kết luận:</strong> {{ $o->result->conclusion }}</div>@endif
                @if ($o->result->attachment_path)
                  <div class="mt-1">
                    @if ($o->result->isImageAttachment())
                      <a href="{{ route('staff.lab-results.attachment', $o->result) }}" target="_blank" class="d-inline-block border rounded p-1 mb-1 bg-white" title="Bấm xem ảnh cỡ lớn">
                        <img src="{{ route('staff.lab-results.attachment', $o->result) }}" alt="Ảnh phim X-Quang" style="max-height: 70px; max-width: 120px; object-fit: cover;" class="rounded">
                      </a>
                      <br>
                    @endif
                    <a href="{{ route('staff.lab-results.attachment', $o->result) }}" target="_blank" class="small text-primary"><i class="bi bi-box-arrow-up-right me-1"></i>{{ $o->result->attachment_name ?? 'Xem tệp đính kèm' }}</a>
                  </div>
                @endif
                <div class="small text-muted">KTV: {{ $o->result->performer?->name ?? '—' }} · {{ $o->result->completed_at?->format('H:i d/m/Y') }}</div>
              @endif
            </div>
          @empty
            <span class="text-muted">Không có chỉ định.</span>
          @endforelse
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
          <span><i class="bi bi-capsule me-1"></i>Đơn thuốc</span>
          @if ($visit->prescription){!! status_badge($visit->prescription->status) !!}@endif
        </div>
        @if ($visit->prescription && $visit->prescription->items->isNotEmpty())
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr><th>#</th><th>Thuốc</th><th class="text-center">SL</th><th>Cách dùng</th><th class="text-center">Ngày</th></tr></thead>
              <tbody>
                @foreach ($visit->prescription->items as $n => $item)
                  <tr>
                    <td>{{ $n + 1 }}</td>
                    <td><strong>{{ $item->medicine->fullName() }}</strong><div class="small text-muted">{{ $item->medicine->active_ingredient }}</div></td>
                    <td class="text-center">{{ $item->quantity }} {{ $item->medicine->unit }}</td>
                    <td class="small">{{ $item->dosage }}@if ($item->usage) - {{ $item->usage }}@endif</td>
                    <td class="text-center">{{ $item->days }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          @if ($visit->prescription->note)<div class="card-footer small"><strong>Lời dặn:</strong> {{ $visit->prescription->note }}</div>@endif
        @else
          <div class="card-body text-muted">Không kê đơn.</div>
        @endif
      </div>

      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-check2-square me-1"></i>Kết luận</div>
        <div class="card-body">
          <p class="mb-1"><strong>Kết luận:</strong> {{ $visit->conclusion ?? '—' }}</p>
          <p class="mb-1"><strong>Lời dặn:</strong> {{ $visit->advice ?? '—' }}</p>
          <p class="mb-0"><strong>Hẹn tái khám:</strong> {{ $visit->follow_up_date?->format('d/m/Y') ?? 'Không' }}</p>
        </div>
      </div>

      @if ($visit->addenda->isNotEmpty())
        <div class="card">
          <div class="card-header"><i class="bi bi-pencil-square me-1"></i>Ghi bổ sung (BR-08)</div>
          <div class="card-body">
            @foreach ($visit->addenda as $a)
              <div class="border-start border-3 border-primary ps-3 mb-2">
                <div class="small text-muted">{{ $a->user->name }} · {{ $a->created_at->format('H:i d/m/Y') }}</div>
                <div style="white-space: pre-line">{{ $a->content }}</div>
              </div>
            @endforeach
          </div>
        </div>
      @endif
    </div>
  </div>
@endsection
