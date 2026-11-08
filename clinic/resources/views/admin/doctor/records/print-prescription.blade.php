@extends('common.layouts.print')

@section('title', 'Đơn thuốc '.$visit->prescription->code)
@section('sheet_class', 'a5')
@section('head_right')
  Mã đơn: <strong>{{ $visit->prescription->code }}</strong><br>
  Mã BN: {{ $visit->patient->code }}
@endsection

@php
  $patient = $visit->patient;
  $rx = $visit->prescription;
@endphp

@section('content')
  <div class="doc-title">ĐƠN THUỐC</div>
  <div class="text-center small mb-3">Lượt khám {{ $visit->code }} · {{ $visit->specialty?->name }}</div>

  <table class="w-100 mb-2" style="font-size: 14px">
    <tr>
      <td>Họ tên: <strong class="text-uppercase">{{ $patient->full_name }}</strong></td>
      <td class="text-end">
        {{ $patient->age !== null ? $patient->age.' tuổi' : '' }}
        {{ $patient->genderLabel() ? ' · '.$patient->genderLabel() : '' }}
      </td>
    </tr>
    <tr><td colspan="2">Địa chỉ: {{ $patient->address ?? '...........................................................' }}</td></tr>
    @if ($patient->insurance_number)<tr><td colspan="2">Số thẻ BHYT: {{ $patient->insurance_number }}</td></tr>@endif
    <tr>
      <td colspan="2">Chẩn đoán:
        {{ $visit->diagnoses->map(fn ($d) => $d->name.' ('.$d->icd10_code.')')->implode('; ') ?: '..........................' }}
      </td>
    </tr>
    @if ($patient->allergies)<tr><td colspan="2"><em>Dị ứng: {{ $patient->allergies }}</em></td></tr>@endif
  </table>

  <ol class="ps-3 mb-3" style="font-size: 14.5px">
    @foreach ($rx->items as $item)
      <li class="mb-2">
        <div class="d-flex justify-content-between">
          <strong>{{ $item->medicine->name }} {{ $item->medicine->strength }}</strong>
          <span>SL: <strong>{{ $item->quantity }}</strong> {{ $item->medicine->unit }}</span>
        </div>
        <div><em>{{ $item->medicine->active_ingredient }}</em></div>
        <div>{{ $item->dosage }}{{ $item->usage ? ' - '.$item->usage : '' }} · {{ $item->days }} ngày</div>
      </li>
    @endforeach
  </ol>

  <div class="mb-1"><strong>Lời dặn:</strong> {{ $rx->note ?: ($visit->advice ?: '..........................................................') }}</div>
  <div class="mb-3"><strong>Hẹn tái khám:</strong> {{ $visit->follow_up_date ? $visit->follow_up_date->format('d/m/Y') : 'Khi có dấu hiệu bất thường' }}</div>

  <div class="row">
    <div class="col-6 small"><em>Khám lại xin mang theo đơn này.</em></div>
    <div class="col-6 sign-box">
      <div><em>Ngày {{ $visit->visit_date->format('d') }} tháng {{ $visit->visit_date->format('m') }} năm {{ $visit->visit_date->format('Y') }}</em></div>
      <div class="fw-bold">Bác sĩ khám bệnh</div>
      <div style="height: 55px"></div>
      <div class="fw-bold">{{ $visit->doctor->display_name }}</div>
    </div>
  </div>
@endsection
