@extends('admin.layouts.app')

@section('title', 'Khám bệnh - '.$patient->full_name)
@section('page_title', 'Bệnh án điện tử')
@section('breadcrumbs')
  <a href="{{ route('staff.exam.index') }}">Khám bệnh</a> / Lượt khám {{ $visit->code }}
@endsection

@php
  $diagCount = $visit->diagnoses->count();
  $activeOrders = $visit->serviceOrders->where('status', '!=', \App\Enums\ServiceOrderStatus::Cancelled);
  $pendingOrders = $visit->serviceOrders->whereIn('status', [\App\Enums\ServiceOrderStatus::Pending, \App\Enums\ServiceOrderStatus::InProgress]);
  $rxCount = $visit->prescription?->items->count() ?? 0;
  $primary = $visit->diagnoses->firstWhere('is_primary', true);
  $tabs = [
      'clinical' => ['bi-heart-pulse', 'Khám lâm sàng', null],
      'diagnoses' => ['bi-journal-code', 'Chẩn đoán', $diagCount],
      'orders' => ['bi-droplet-half', 'Chỉ định CLS', $activeOrders->count()],
      'prescription' => ['bi-capsule', 'Đơn thuốc', $rxCount],
      'history' => ['bi-clock-history', 'Tiền sử', $history->count()],
      'conclusion' => ['bi-check2-square', 'Kết luận & hoàn thành', null],
  ];
  $tab = array_key_exists($tab, $tabs) ? $tab : 'clinical';
@endphp

@section('content')
  {{-- Thông tin bệnh nhân --}}
  <div class="patient-banner mb-3">
    <div class="row align-items-center g-3">
      <div class="col-lg">
        <div class="d-flex align-items-center gap-3">
          <div class="queue-no bg-white">{{ $visit->queue_number }}</div>
          <div>
            <h2 class="h5 mb-1">{{ $patient->full_name }} <small class="opacity-75 fw-normal">· {{ $patient->code }}</small></h2>
            <div class="small opacity-75">
              {{ $patient->genderLabel() ?: 'Chưa rõ giới tính' }}
              @if ($patient->date_of_birth) · {{ $patient->date_of_birth->format('d/m/Y') }} ({{ $patient->age }} tuổi) @endif
              @if ($patient->phone) · <i class="bi bi-telephone"></i> {{ $patient->phone }} @endif
              @if ($patient->blood_type) · Nhóm máu {{ $patient->blood_type }} @endif
              @if ($patient->insurance_number) · BHYT {{ $patient->insurance_number }} @endif
            </div>
            @if ($patient->address)<div class="small opacity-75"><i class="bi bi-geo-alt"></i> {{ $patient->address }}</div>@endif
          </div>
        </div>
      </div>
      <div class="col-lg-auto text-lg-end">
        <div class="mb-2">
          {!! status_badge($visit->status) !!}
          <span class="badge bg-light text-dark">{{ $visit->type === 'walk_in' ? 'Vãng lai' : 'Có lịch hẹn' }}</span>
          @if ($visit->is_priority)<span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> Ưu tiên</span>@endif
        </div>
        <div class="small opacity-75 mb-2">
          Lượt khám {{ $visit->code }} · {{ $visit->visit_date->format('d/m/Y') }}
          @if ($visit->checked_in_at) · Check-in {{ $visit->checked_in_at->format('H:i') }} @endif
        </div>
        <div class="d-flex flex-wrap gap-1 justify-content-lg-end">
          @if ($visit->status === \App\Enums\VisitStatus::InProgress)
            <form method="POST" action="{{ route('staff.exam.recall', $visit) }}">@csrf
              <button class="btn btn-sm btn-light"><i class="bi bi-megaphone me-1"></i>Gọi lại</button>
            </form>
          @endif
          @if ($rxCount)
            <a href="{{ route('staff.records.print-prescription', $visit) }}" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i>In đơn thuốc</a>
          @endif
          @if ($activeOrders->isNotEmpty())
            <a href="{{ route('staff.records.print-orders', $visit) }}" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i>In phiếu chỉ định</a>
          @endif
          <a href="{{ route('staff.exam.index') }}" class="btn btn-sm btn-outline-light"><i class="bi bi-list-ol me-1"></i>Hàng đợi</a>
        </div>
      </div>
    </div>
  </div>

  @if ($patient->allergies)
    <div class="allergy-alert mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Dị ứng:</strong> {{ $patient->allergies }}</div>
  @endif
  @if ($patient->medical_history)
    <div class="alert alert-light border py-2 mb-2"><i class="bi bi-clipboard2-heart me-1"></i><strong>Tiền sử bệnh:</strong> {{ $patient->medical_history }}</div>
  @endif

  @if ($visit->status === \App\Enums\VisitStatus::Waiting)
    <div class="alert alert-warning d-flex align-items-center justify-content-between gap-2">
      <span><i class="bi bi-hourglass-split me-1"></i>Bệnh nhân đang chờ trong hàng đợi. Gọi vào khám để bắt đầu ghi bệnh án.</span>
      <form method="POST" action="{{ route('staff.exam.call', $visit) }}">@csrf
        <button class="btn btn-sm btn-primary"><i class="bi bi-megaphone me-1"></i>Gọi vào khám</button>
      </form>
    </div>
  @elseif ($visit->isLocked())
    <div class="locked-badge mb-3">
      <i class="bi bi-lock-fill me-1"></i>Bệnh án đã hoàn thành lúc {{ $visit->completed_at?->format('H:i d/m/Y') }} và đã khóa.
      Mọi chỉnh sửa chỉ được thực hiện bằng ghi bổ sung (BR-08).
      @if ($visit->invoice) · Hóa đơn {{ $visit->invoice->code }}: {!! status_badge($visit->invoice->status) !!} @endif
    </div>
  @elseif ($visit->status === \App\Enums\VisitStatus::AwaitingResults)
    <div class="alert alert-info py-2"><i class="bi bi-droplet-half me-1"></i>Đang chờ kết quả cận lâm sàng ({{ $pendingOrders->count() }} chỉ định). Bạn có thể khám bệnh nhân khác trong lúc chờ.</div>
  @endif

  <ul class="nav nav-tabs" role="tablist">
    @foreach ($tabs as $key => [$icon, $label, $count])
      <li class="nav-item" role="presentation">
        <button class="nav-link {{ $tab === $key ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}" type="button" role="tab">
          <i class="bi {{ $icon }} me-1"></i>{{ $label }}
          @if ($count)<span class="badge rounded-pill bg-secondary-subtle text-secondary ms-1">{{ $count }}</span>@endif
        </button>
      </li>
    @endforeach
  </ul>

  <div class="tab-content card card-body rounded-top-0 mb-3">

    {{-- (1) Khám lâm sàng - BS-05 --}}
    <div class="tab-pane fade {{ $tab === 'clinical' ? 'show active' : '' }}" id="tab-clinical" role="tabpanel">
      <form method="POST" action="{{ route('staff.exam.clinical', $visit) }}">
        @csrf @method('PUT')
        <input type="hidden" name="tab" value="clinical">
        <fieldset @disabled(! $canEdit)>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Lý do khám</label>
              <input type="text" name="reason" value="{{ old('reason', $visit->reason) }}" class="form-control" maxlength="500">
            </div>
            <div class="col-md-6">
              <label class="form-label">Triệu chứng</label>
              <input type="text" name="symptoms" value="{{ old('symptoms', $visit->symptoms) }}" class="form-control" maxlength="2000">
            </div>
          </div>

          <h6 class="mt-4 mb-2"><i class="bi bi-activity me-1"></i>Sinh hiệu</h6>
          <div class="row g-2">
            @foreach ([
                ['pulse', 'Mạch', 'lần/phút', 1],
                ['temperature', 'Nhiệt độ', '°C', 0.1],
                ['respiratory_rate', 'Nhịp thở', 'lần/phút', 1],
                ['spo2', 'SpO2', '%', 1],
                ['weight', 'Cân nặng', 'kg', 0.1],
                ['height', 'Chiều cao', 'cm', 0.1],
            ] as [$field, $label, $unit, $step])
              <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label small">{{ $label }}</label>
                <div class="input-group input-group-sm vital-input">
                  <input type="number" step="{{ $step }}" name="{{ $field }}" id="v-{{ $field }}" value="{{ old($field, $visit->{$field}) }}" class="form-control @error($field) is-invalid @enderror">
                  <span class="input-group-text">{{ $unit }}</span>
                </div>
              </div>
            @endforeach
            <div class="col-12 col-md-6 col-xl-4">
              <label class="form-label small">Huyết áp (tâm thu / tâm trương)</label>
              <div class="input-group input-group-sm vital-input">
                <input type="number" name="bp_systolic" value="{{ old('bp_systolic', $visit->bp_systolic) }}" class="form-control @error('bp_systolic') is-invalid @enderror" placeholder="120">
                <span class="input-group-text">/</span>
                <input type="number" name="bp_diastolic" value="{{ old('bp_diastolic', $visit->bp_diastolic) }}" class="form-control @error('bp_diastolic') is-invalid @enderror" placeholder="80">
                <span class="input-group-text">mmHg</span>
              </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
              <label class="form-label small">BMI</label>
              <div class="form-control form-control-sm bg-light" id="bmiValue">{{ $visit->bmi() ?? '—' }}</div>
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label">Khám lâm sàng</label>
            <textarea name="clinical_notes" rows="5" class="form-control" placeholder="Toàn thân, các cơ quan, dấu hiệu bệnh lý...">{{ old('clinical_notes', $visit->clinical_notes) }}</textarea>
          </div>

          @if ($canEdit)
            <div class="text-end mt-3">
              <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu khám lâm sàng</button>
            </div>
          @endif
        </fieldset>
      </form>
    </div>

    {{-- (2) Chẩn đoán ICD-10 - BS-06 --}}
    <div class="tab-pane fade {{ $tab === 'diagnoses' ? 'show active' : '' }}" id="tab-diagnoses" role="tabpanel">
      @if ($canEdit)
        <form method="POST" action="{{ route('staff.exam.diagnoses', $visit) }}" id="diagForm">
          @csrf @method('PUT')
          <p class="text-muted small mb-2">Gõ mã hoặc tên bệnh để tra cứu danh mục bệnh lý. Chọn đúng một bệnh chính, các dòng còn lại là bệnh kèm theo.</p>
          <div class="table-responsive" style="overflow: visible">
            <table class="table align-middle" id="diagTable">
              <thead>
                <tr><th style="width:80px" class="text-center">Bệnh chính</th><th style="width:220px">Mã bệnh</th><th>Tên bệnh</th><th style="width:28%">Ghi chú</th><th style="width:50px"></th></tr>
              </thead>
              <tbody>
                @foreach ($visit->diagnoses as $i => $d)
                  <tr>
                    <td class="text-center"><input type="radio" class="form-check-input" name="primary" value="{{ $i }}" @checked($d->is_primary)></td>
                    <td><div class="ac-wrap"><input type="text" name="diagnoses[{{ $i }}][icd10_code]" value="{{ $d->icd10_code }}" class="form-control form-control-sm icd-code" autocomplete="off" required><div class="ac-list d-none"></div></div></td>
                    <td><input type="text" name="diagnoses[{{ $i }}][name]" value="{{ $d->name }}" class="form-control form-control-sm icd-name" required></td>
                    <td><input type="text" name="diagnoses[{{ $i }}][note]" value="{{ $d->note }}" class="form-control form-control-sm"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger row-remove" title="Xóa dòng"><i class="bi bi-x-lg"></i></button></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-sm btn-outline-primary" id="addDiag"><i class="bi bi-plus-lg me-1"></i>Thêm chẩn đoán</button>
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu chẩn đoán</button>
          </div>
        </form>
      @else
        @forelse ($visit->diagnoses as $d)
          <div class="d-flex gap-2 align-items-start py-2 border-bottom">
            <span class="badge {{ $d->is_primary ? 'bg-primary' : 'bg-secondary' }}">{{ $d->is_primary ? 'Bệnh chính' : 'Kèm theo' }}</span>
            <div><strong>{{ $d->icd10_code }}</strong> - {{ $d->name }} @if ($d->note)<div class="small text-muted">{{ $d->note }}</div>@endif</div>
          </div>
        @empty
          <div class="empty-state"><i class="bi bi-journal-code"></i>Chưa có chẩn đoán.</div>
        @endforelse
      @endif
    </div>

    {{-- (3) Chỉ định cận lâm sàng - BS-07, BS-08 --}}
    <div class="tab-pane fade {{ $tab === 'orders' ? 'show active' : '' }}" id="tab-orders" role="tabpanel">
      @if ($visit->serviceOrders->isNotEmpty())
        <h6 class="mb-2">Chỉ định đã thực hiện</h6>
        <div class="table-responsive mb-3">
          <table class="table table-hover align-middle">
            <thead><tr><th>Dịch vụ</th><th class="text-end">Giá</th><th>Trạng thái</th><th style="width:45%">Kết quả</th><th></th></tr></thead>
            <tbody>
              @foreach ($visit->serviceOrders as $o)
                <tr>
                  <td>
                    <div class="fw-semibold">{{ $o->service->name }}</div>
                    <small class="text-muted">{{ $o->service->type->label() }} · {{ $o->created_at->format('H:i d/m') }}</small>
                    @if ($o->note)<div class="small text-muted">Ghi chú: {{ $o->note }}</div>@endif
                  </td>
                  <td class="text-end">{{ money($o->price) }}</td>
                  <td>{!! status_badge($o->status) !!}</td>
                  <td>
                    @if ($o->result)
                      <div class="small" style="white-space: pre-line">{{ $o->result->result }}</div>
                      @if ($o->result->conclusion)<div class="small mt-1"><strong>Kết luận:</strong> {{ $o->result->conclusion }}</div>@endif
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
                      <div class="small text-muted">{{ $o->result->performer?->name }} · {{ $o->result->completed_at?->format('H:i d/m') }}</div>
                    @else
                      <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small">Chưa có kết quả</span>
                        @if ($canEdit)
                          <a href="{{ route('staff.lab.show', $o) }}" class="btn btn-sm btn-outline-primary py-0 px-2" title="Bác sĩ nhập kết quả hoặc tải ảnh phim lên">
                            <i class="bi bi-upload me-1"></i>Tải phim / Nhập KQ
                          </a>
                        @endif
                      </div>
                    @endif
                  </td>
                  <td class="text-end">
                    @if ($canEdit && $o->status === \App\Enums\ServiceOrderStatus::Pending)
                      <form method="POST" action="{{ route('staff.exam.orders.cancel', $o) }}" data-confirm="Hủy chỉ định {{ $o->service->name }}?">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" title="Hủy chỉ định"><i class="bi bi-x-lg"></i></button>
                      </form>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif

      @if ($canEdit)
        <form method="POST" action="{{ route('staff.exam.orders', $visit) }}" id="orderForm">
          @csrf
          <h6 class="mb-2">Chỉ định mới</h6>
          <div class="row g-3">
            @foreach ($services as $type => $items)
              <div class="col-md-6 col-xl-4">
                <div class="border rounded p-2 h-100">
                  <div class="fw-semibold small text-uppercase text-muted mb-1">{{ $serviceTypes[$type] ?? $type }}</div>
                  @foreach ($items as $s)
                    @php $already = in_array($s->id, $orderedServiceIds, true); @endphp
                    <div class="form-check">
                      <input class="form-check-input order-check" type="checkbox" name="service_ids[]" value="{{ $s->id }}" id="svc{{ $s->id }}" data-price="{{ $s->price }}" @disabled($already)>
                      <label class="form-check-label small d-flex justify-content-between gap-2 {{ $already ? 'text-muted' : '' }}" for="svc{{ $s->id }}">
                        <span>{{ $s->name }} @if ($already)<em>(đã chỉ định)</em>@endif</span>
                        <span class="text-nowrap">{{ money($s->price) }}</span>
                      </label>
                    </div>
                  @endforeach
                </div>
              </div>
            @endforeach
          </div>
          <div class="row g-2 mt-2 align-items-end">
            <div class="col-md">
              <label class="form-label">Ghi chú cho kỹ thuật viên</label>
              <input type="text" name="note" class="form-control" maxlength="500" placeholder="Ví dụ: nhịn ăn sáng, chụp tư thế thẳng...">
            </div>
            <div class="col-md-auto">
              <button class="btn btn-primary" id="orderSubmit" disabled><i class="bi bi-send me-1"></i>Chỉ định <span id="orderSummary"></span></button>
            </div>
          </div>
        </form>
      @elseif ($visit->serviceOrders->isEmpty())
        <div class="empty-state"><i class="bi bi-droplet"></i>Không có chỉ định cận lâm sàng.</div>
      @endif
    </div>

    {{-- (4) Đơn thuốc - BS-09, BR-10 --}}
    <div class="tab-pane fade {{ $tab === 'prescription' ? 'show active' : '' }}" id="tab-prescription" role="tabpanel">
      @if ($rxEditable)
        <form method="POST" action="{{ route('staff.exam.prescription', $visit) }}" id="rxForm">
          @csrf @method('PUT')
          <div class="table-responsive" style="overflow: visible">
            <table class="table align-middle" id="rxTable">
              <thead>
                <tr>
                  <th style="width:36px">#</th><th style="min-width:240px">Thuốc</th><th style="width:90px">SL</th><th style="width:70px">ĐVT</th>
                  <th style="width:140px">Liều mỗi lần</th><th style="min-width:200px">Cách dùng</th><th style="width:80px">Số ngày</th>
                  <th class="text-end" style="width:110px">Thành tiền</th><th style="width:44px"></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <datalist id="dosageList">
            <option value="1 viên/lần"><option value="2 viên/lần"><option value="1/2 viên/lần"><option value="1 gói/lần"><option value="2 nhát xịt/lần"><option value="1-2 giọt/lần"><option value="Bôi lớp mỏng">
          </datalist>
          <datalist id="usageList">
            <option value="Uống ngày 2 lần sáng - tối, sau ăn"><option value="Uống ngày 3 lần sau ăn"><option value="Uống ngày 1 lần buổi sáng"><option value="Uống ngày 1 lần buổi tối"><option value="Uống trước ăn 30 phút"><option value="Uống khi sốt trên 38.5°C, cách nhau 4-6 giờ"><option value="Nhỏ mắt ngày 3 lần"><option value="Bôi ngày 2 lần">
          </datalist>
          <div id="rxWarnings" class="alert alert-warning d-none py-2"></div>
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="addRx"><i class="bi bi-plus-lg me-1"></i>Thêm thuốc</button>
            <div class="fs-6">Tổng tiền thuốc: <strong id="rxTotal">0 đ</strong></div>
          </div>
          <div class="mt-3">
            <label class="form-label">Lời dặn trên đơn</label>
            <input type="text" name="note" value="{{ old('note', $visit->prescription?->note) }}" class="form-control" maxlength="500" placeholder="Ví dụ: Tái khám sau 7 ngày, uống nhiều nước...">
          </div>
          <div class="text-end mt-3">
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu đơn thuốc</button>
          </div>
        </form>
      @elseif ($visit->prescription && $visit->prescription->items->isNotEmpty())
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>Đơn thuốc <strong>{{ $visit->prescription->code }}</strong> {!! status_badge($visit->prescription->status) !!}</div>
          @if ($visit->prescription->dispensed_at)
            <small class="text-muted">Cấp phát lúc {{ $visit->prescription->dispensed_at->format('H:i d/m/Y') }}</small>
          @endif
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>#</th><th>Thuốc</th><th class="text-center">SL</th><th>Liều - cách dùng</th><th class="text-center">Số ngày</th><th class="text-end">Thành tiền</th></tr></thead>
            <tbody>
              @foreach ($visit->prescription->items as $n => $item)
                <tr>
                  <td>{{ $n + 1 }}</td>
                  <td><strong>{{ $item->medicine->fullName() }}</strong><div class="small text-muted">{{ $item->medicine->active_ingredient }}</div></td>
                  <td class="text-center">{{ $item->quantity }} {{ $item->medicine->unit }}</td>
                  <td>{{ $item->dosage }}@if ($item->usage) - {{ $item->usage }}@endif</td>
                  <td class="text-center">{{ $item->days }}</td>
                  <td class="text-end">{{ money($item->amount) }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot><tr><th colspan="5" class="text-end">Tổng tiền thuốc</th><th class="text-end">{{ money($visit->prescription->items->sum('amount')) }}</th></tr></tfoot>
          </table>
        </div>
        @if ($visit->prescription->note)<div class="small"><strong>Lời dặn:</strong> {{ $visit->prescription->note }}</div>@endif
      @else
        <div class="empty-state"><i class="bi bi-capsule"></i>Không kê đơn thuốc.</div>
      @endif
    </div>

    {{-- (5) Tiền sử khám - BS-04 --}}
    <div class="tab-pane fade {{ $tab === 'history' ? 'show active' : '' }}" id="tab-history" role="tabpanel">
      @forelse ($history as $h)
        <div class="border rounded p-3 mb-2">
          <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
              <strong>{{ $h->visit_date->format('d/m/Y') }}</strong> · {{ $h->specialty?->name }} · {{ $h->doctor->display_name }}
            </div>
            <a href="{{ route('staff.records.show', $h) }}" class="small" target="_blank">Xem bệnh án <i class="bi bi-box-arrow-up-right"></i></a>
          </div>
          @if ($h->diagnoses->isNotEmpty())
            <div class="small mt-1"><i class="bi bi-journal-code me-1"></i>
              @foreach ($h->diagnoses as $d)<span class="me-2"><strong>{{ $d->icd10_code }}</strong> {{ $d->name }}{{ $d->is_primary ? ' (chính)' : '' }}</span>@endforeach
            </div>
          @endif
          @if ($h->vitalsSummary())<div class="small text-muted mt-1"><i class="bi bi-activity me-1"></i>{{ $h->vitalsSummary() }}</div>@endif
          @if ($h->prescription && $h->prescription->items->isNotEmpty())
            <div class="small mt-1"><i class="bi bi-capsule me-1"></i>
              {{ $h->prescription->items->map(fn ($i) => $i->medicine->fullName().' x'.$i->quantity)->implode('; ') }}
            </div>
          @endif
          @if ($h->conclusion)<div class="small mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ $h->conclusion }}</div>@endif
        </div>
      @empty
        <div class="empty-state"><i class="bi bi-clock-history"></i>Đây là lần khám đầu tiên của bệnh nhân tại phòng khám.</div>
      @endforelse
    </div>

    {{-- (6) Kết luận, dặn dò, tái khám, hoàn thành - BS-11, BS-12 --}}
    <div class="tab-pane fade {{ $tab === 'conclusion' ? 'show active' : '' }}" id="tab-conclusion" role="tabpanel">
      <div class="row g-4">
        <div class="col-lg-8">
          <form method="POST" action="{{ route('staff.exam.clinical', $visit) }}">
            @csrf @method('PUT')
            <input type="hidden" name="tab" value="conclusion">
            <fieldset @disabled(! $canEdit)>
              <div class="mb-3">
                <label class="form-label">Kết luận</label>
                <textarea name="conclusion" rows="3" class="form-control">{{ old('conclusion', $visit->conclusion) }}</textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Lời dặn của bác sĩ</label>
                <textarea name="advice" rows="3" class="form-control" placeholder="Chế độ ăn uống, sinh hoạt, dấu hiệu cần tái khám ngay...">{{ old('advice', $visit->advice) }}</textarea>
              </div>
              <div class="row g-2 align-items-end">
                <div class="col-md-5">
                  <label class="form-label">Hẹn tái khám</label>
                  <input type="date" name="follow_up_date" value="{{ old('follow_up_date', $visit->follow_up_date?->toDateString()) }}" min="{{ today()->addDay()->toDateString() }}" class="form-control @error('follow_up_date') is-invalid @enderror">
                </div>
                <div class="col-md">
                  <div class="form-text">Khi hoàn thành, hệ thống tự đặt lịch tái khám vào khung giờ trống đầu tiên của bác sĩ trong ngày hẹn.</div>
                </div>
              </div>
              @if ($canEdit)
                <div class="text-end mt-3">
                  <button class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Lưu kết luận</button>
                </div>
              @endif
            </fieldset>
          </form>
        </div>

        <div class="col-lg-4">
          <div class="border rounded p-3 bg-light">
            <h6 class="mb-3">Kiểm tra trước khi hoàn thành</h6>
            <ul class="list-unstyled small mb-3">
              <li class="mb-1">
                <i class="bi {{ $primary ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }} me-1"></i>
                Chẩn đoán bệnh chính: {{ $primary ? $primary->icd10_code.' - '.$primary->name : 'chưa có' }}
              </li>
              <li class="mb-1">
                <i class="bi {{ $pendingOrders->isEmpty() ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }} me-1"></i>
                Chỉ định CLS: {{ $activeOrders->count() }} ({{ $pendingOrders->count() }} chưa có kết quả)
              </li>
              <li class="mb-1"><i class="bi bi-info-circle-fill text-primary me-1"></i>Đơn thuốc: {{ $rxCount }} thuốc</li>
              <li><i class="bi bi-info-circle-fill text-primary me-1"></i>Tái khám: {{ $visit->follow_up_date?->format('d/m/Y') ?? 'không hẹn' }}</li>
            </ul>
            @if ($canEdit)
              <form method="POST" action="{{ route('staff.exam.complete', $visit) }}"
                    data-confirm="Hoàn thành lượt khám? Bệnh án sẽ bị khóa và hóa đơn được chuyển tới thu ngân.">
                @csrf
                <button class="btn btn-success w-100" @disabled(! $primary || $pendingOrders->isNotEmpty())><i class="bi bi-check2-all me-1"></i>Hoàn thành lượt khám</button>
              </form>
              <div class="form-text">Bệnh án sẽ bị khóa (BR-08), hóa đơn gồm phí khám, CLS và thuốc được lập tự động.</div>
            @elseif ($visit->isLocked())
              <div class="small text-success"><i class="bi bi-lock-fill me-1"></i>Đã hoàn thành lúc {{ $visit->completed_at?->format('H:i d/m/Y') }}.</div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Ghi bổ sung bệnh án đã khóa - BR-08 --}}
  @if ($visit->isLocked())
    <div class="card">
      <div class="card-header"><i class="bi bi-pencil-square me-1"></i>Ghi bổ sung bệnh án</div>
      <div class="card-body">
        @foreach ($visit->addenda as $a)
          <div class="border-start border-3 border-primary ps-3 mb-3">
            <div class="small text-muted">{{ $a->user->name }} · {{ $a->created_at->format('H:i d/m/Y') }}</div>
            <div style="white-space: pre-line">{{ $a->content }}</div>
          </div>
        @endforeach
        <form method="POST" action="{{ route('staff.exam.addendum', $visit) }}">
          @csrf
          <textarea name="content" rows="3" class="form-control mb-2" required maxlength="5000" placeholder="Nội dung bổ sung (được ghi lại người và thời điểm, không sửa/xóa được)"></textarea>
          <div class="text-end"><button class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Ghi bổ sung</button></div>
        </form>
      </div>
    </div>
  @endif
@endsection

@push('scripts')
  <script>
    (function () {
      // ---------- Tự hoàn thành dùng chung ----------
      function autocomplete(input, opts) {
        const list = input.parentElement.querySelector('.ac-list');
        let timer = null, items = [], active = -1;
        const hide = () => { list.classList.add('d-none'); active = -1; };
        const highlight = () => list.querySelectorAll('.ac-item[data-i]').forEach((el, i) => el.classList.toggle('active', i === active));
        input.addEventListener('input', () => {
          clearTimeout(timer);
          const q = input.value.trim();
          if (opts.onType) opts.onType();
          if (!q) { hide(); return; }
          timer = setTimeout(async () => {
            try { items = await App.fetchJson(opts.url + '?q=' + encodeURIComponent(q)); } catch (e) { items = []; }
            active = -1;
            list.innerHTML = items.length
              ? items.map((it, i) => '<div class="ac-item" data-i="' + i + '">' + opts.render(it) + '</div>').join('')
              : '<div class="ac-item text-muted">Không tìm thấy</div>';
            list.classList.remove('d-none');
          }, 220);
        });
        list.addEventListener('mousedown', (e) => {
          const el = e.target.closest('.ac-item[data-i]');
          if (el) { e.preventDefault(); opts.onSelect(items[el.dataset.i]); hide(); }
        });
        input.addEventListener('keydown', (e) => {
          if (list.classList.contains('d-none')) return;
          const max = list.querySelectorAll('.ac-item[data-i]').length - 1;
          if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(max, active + 1); highlight(); }
          else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(0, active - 1); highlight(); }
          else if (e.key === 'Enter') { e.preventDefault(); if (active >= 0) { opts.onSelect(items[active]); hide(); } }
          else if (e.key === 'Escape') { hide(); }
        });
        input.addEventListener('blur', () => setTimeout(hide, 150));
      }

      // ---------- BMI ----------
      const w = document.getElementById('v-weight'), h = document.getElementById('v-height'), bmi = document.getElementById('bmiValue');
      const calcBmi = () => {
        const kg = parseFloat(w.value), cm = parseFloat(h.value);
        if (kg > 0 && cm > 0) {
          const v = kg / Math.pow(cm / 100, 2);
          const label = v < 18.5 ? 'gầy' : v < 23 ? 'bình thường' : v < 25 ? 'thừa cân' : 'béo phì';
          bmi.textContent = v.toFixed(1) + ' (' + label + ')';
        } else { bmi.textContent = '—'; }
      };
      if (w && h) { w.addEventListener('input', calcBmi); h.addEventListener('input', calcBmi); calcBmi(); }

      // ---------- Chẩn đoán ICD-10 ----------
      const diagTable = document.getElementById('diagTable');
      if (diagTable) {
        const tbody = diagTable.querySelector('tbody');
        let diagIndex = tbody.rows.length;
        const bindDiagRow = (tr) => {
          const code = tr.querySelector('.icd-code'), name = tr.querySelector('.icd-name');
          autocomplete(code, {
            url: @json(route('staff.icd10.search')),
            render: (it) => '<strong>' + App.escape(it.code) + '</strong> <small>' + App.escape(it.name) + '</small>',
            onSelect: (it) => { code.value = it.code; name.value = it.name; name.focus(); },
          });
          tr.querySelector('.row-remove').addEventListener('click', () => tr.remove());
        };
        const addDiagRow = () => {
          const i = diagIndex++;
          const tr = document.createElement('tr');
          tr.innerHTML =
            '<td class="text-center"><input type="radio" class="form-check-input" name="primary" value="' + i + '"></td>' +
            '<td><div class="ac-wrap"><input type="text" name="diagnoses[' + i + '][icd10_code]" class="form-control form-control-sm icd-code" autocomplete="off" placeholder="Mã hoặc tên bệnh" required><div class="ac-list d-none"></div></div></td>' +
            '<td><input type="text" name="diagnoses[' + i + '][name]" class="form-control form-control-sm icd-name" required></td>' +
            '<td><input type="text" name="diagnoses[' + i + '][note]" class="form-control form-control-sm"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger row-remove" title="Xóa dòng"><i class="bi bi-x-lg"></i></button></td>';
          tbody.appendChild(tr);
          bindDiagRow(tr);
          if (!tbody.querySelector('input[name=primary]:checked')) tr.querySelector('input[name=primary]').checked = true;
          tr.querySelector('.icd-code').focus();
        };
        Array.from(tbody.rows).forEach(bindDiagRow);
        document.getElementById('addDiag').addEventListener('click', addDiagRow);
        if (!tbody.rows.length) addDiagRow();
        document.getElementById('diagForm').addEventListener('submit', () => {
          if (tbody.rows.length && !tbody.querySelector('input[name=primary]:checked')) tbody.querySelector('input[name=primary]').checked = true;
        });
      }

      // ---------- Chỉ định CLS ----------
      const orderForm = document.getElementById('orderForm');
      if (orderForm) {
        const update = () => {
          const checked = orderForm.querySelectorAll('.order-check:checked');
          const total = Array.from(checked).reduce((s, el) => s + Number(el.dataset.price), 0);
          document.getElementById('orderSummary').textContent = checked.length ? '(' + checked.length + ' dịch vụ · ' + App.money(total) + ')' : '';
          document.getElementById('orderSubmit').disabled = !checked.length;
        };
        orderForm.addEventListener('change', update);
        update();
      }

      // ---------- Kê đơn thuốc ----------
      const rxTable = document.getElementById('rxTable');
      if (rxTable) {
        const tbody = rxTable.querySelector('tbody');
        const allergies = @json(array_values(array_map('mb_strtolower', $patient->allergyList())));
        let rxIndex = 0;

        const refresh = () => {
          let total = 0;
          const warnings = [];
          Array.from(tbody.rows).forEach((tr, n) => {
            tr.querySelector('.rx-no').textContent = n + 1;
            const id = tr.querySelector('.rx-id').value;
            const qty = parseInt(tr.querySelector('.rx-qty').value || '0', 10);
            const price = Number(tr.dataset.price || 0);
            const amount = id ? qty * price : 0;
            total += amount;
            tr.querySelector('.rx-amount').textContent = id && qty ? App.money(amount) : '';
            tr.querySelector('.rx-unit').textContent = tr.dataset.unit || '';
            let level = '';
            if (id) {
              const name = tr.querySelector('.rx-name').value;
              const stock = tr.dataset.stock === '' ? null : Number(tr.dataset.stock);
              if (stock !== null && qty > stock) {
                warnings.push('Thuốc <strong>' + App.escape(name) + '</strong>: kê ' + qty + ' nhưng tồn kho còn hạn chỉ còn ' + stock + '.');
                level = 'table-warning';
              }
              const hay = (name + ' ' + (tr.dataset.ingredient || '')).toLowerCase();
              allergies.forEach((a) => {
                if (a && hay.includes(a)) {
                  warnings.push('<strong class="text-danger">CẢNH BÁO DỊ ỨNG:</strong> bệnh nhân dị ứng "' + App.escape(a) + '" - thuốc <strong>' + App.escape(name) + '</strong>.');
                  level = 'table-danger';
                }
              });
            }
            tr.className = level;
          });
          document.getElementById('rxTotal').textContent = App.money(total);
          const box = document.getElementById('rxWarnings');
          box.innerHTML = warnings.length ? '<i class="bi bi-exclamation-triangle me-1"></i>' + warnings.join('<br>') : '';
          box.classList.toggle('d-none', !warnings.length);
        };

        const setMedicine = (tr, m) => {
          tr.querySelector('.rx-id').value = m ? m.id : '';
          tr.dataset.price = m ? m.price : 0;
          tr.dataset.stock = m ? m.stock : '';
          tr.dataset.unit = m ? m.unit : '';
          tr.dataset.ingredient = m ? (m.active_ingredient || '') : '';
          tr.querySelector('.rx-info').textContent = m ? (m.active_ingredient || '') + ' · ' + App.money(m.price) + '/' + m.unit + ' · tồn ' + m.stock : '';
        };

        const addRow = (item) => {
          item = item || {};
          const i = rxIndex++;
          const tr = document.createElement('tr');
          tr.innerHTML =
            '<td class="rx-no text-muted"></td>' +
            '<td><div class="ac-wrap"><input type="text" class="form-control form-control-sm rx-name" autocomplete="off" placeholder="Tìm tên thuốc hoặc hoạt chất" value="' + App.escape(item.name || '') + '"><div class="ac-list d-none"></div></div>' +
            '<input type="hidden" class="rx-id" name="items[' + i + '][medicine_id]" value="' + App.escape(item.medicine_id || '') + '">' +
            '<div class="small text-muted rx-info"></div></td>' +
            '<td><input type="number" min="1" max="1000" class="form-control form-control-sm rx-qty" name="items[' + i + '][quantity]" value="' + App.escape(item.quantity || '') + '"></td>' +
            '<td class="rx-unit small"></td>' +
            '<td><input type="text" class="form-control form-control-sm" list="dosageList" name="items[' + i + '][dosage]" value="' + App.escape(item.dosage || '') + '" placeholder="1 viên/lần"></td>' +
            '<td><input type="text" class="form-control form-control-sm" list="usageList" name="items[' + i + '][usage]" value="' + App.escape(item.usage || '') + '"></td>' +
            '<td><input type="number" min="1" max="365" class="form-control form-control-sm" name="items[' + i + '][days]" value="' + App.escape(item.days || '') + '"></td>' +
            '<td class="text-end rx-amount small fw-semibold"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger rx-remove" title="Xóa thuốc"><i class="bi bi-x-lg"></i></button></td>';
          tbody.appendChild(tr);
          if (item.medicine_id) {
            setMedicine(tr, { id: item.medicine_id, price: item.price, stock: item.stock, unit: item.unit, active_ingredient: item.active_ingredient });
          }
          const name = tr.querySelector('.rx-name');
          autocomplete(name, {
            url: @json(route('staff.medicines.search')),
            render: (m) => '<strong>' + App.escape(m.full_name) + '</strong> <small>' + App.escape(m.active_ingredient || '') +
              ' · ' + App.money(m.price) + '/' + App.escape(m.unit) + ' · tồn ' + m.stock + '</small>',
            onType: () => { setMedicine(tr, null); refresh(); },
            onSelect: (m) => {
              name.value = m.full_name;
              setMedicine(tr, m);
              const qty = tr.querySelector('.rx-qty');
              if (!qty.value) qty.value = 1;
              qty.focus(); qty.select();
              refresh();
            },
          });
          tr.querySelector('.rx-remove').addEventListener('click', () => { tr.remove(); refresh(); });
          tr.addEventListener('input', refresh);
          refresh();
          return tr;
        };

        const initial = @json($rxItems);
        initial.forEach(addRow);
        if (!initial.length) addRow();
        document.getElementById('addRx').addEventListener('click', () => addRow().querySelector('.rx-name').focus());
      }
    })();
  </script>
@endpush
