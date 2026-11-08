@extends('common.layouts.print')

@section('title', 'Phiếu chỉ định '.$visit->code)
@section('sheet_class', 'a5')
@section('head_right')
  Lượt khám: <strong>{{ $visit->code }}</strong><br>
  Mã BN: {{ $visit->patient->code }}
@endsection

@php $patient = $visit->patient; @endphp

@section('content')
  <div class="doc-title">PHIẾU CHỈ ĐỊNH CẬN LÂM SÀNG</div>
  <div class="text-center small mb-3">{{ $visit->specialty?->name }} · Phòng {{ $visit->room?->code ?? $visit->doctor->room?->code ?? '—' }}</div>

  <table class="w-100 mb-2" style="font-size: 14px">
    <tr>
      <td>Họ tên: <strong class="text-uppercase">{{ $patient->full_name }}</strong></td>
      <td class="text-end">{{ $patient->age !== null ? $patient->age.' tuổi' : '' }}{{ $patient->genderLabel() ? ' · '.$patient->genderLabel() : '' }}</td>
    </tr>
    <tr><td colspan="2">Địa chỉ: {{ $patient->address ?? '..........................................................' }}</td></tr>
    <tr><td colspan="2">Chẩn đoán sơ bộ: {{ $visit->diagnoses->map(fn ($d) => $d->name.' ('.$d->icd10_code.')')->implode('; ') ?: ($visit->reason ?? '..........................') }}</td></tr>
  </table>

  <table class="print-table mb-3">
    <thead><tr><th style="width:36px">STT</th><th>Dịch vụ</th><th>Loại</th><th>Ghi chú</th><th style="width:90px">Đơn giá</th></tr></thead>
    <tbody>
      @foreach ($orders as $n => $o)
        <tr>
          <td class="text-center">{{ $n + 1 }}</td>
          <td>{{ $o->service->name }}</td>
          <td>{{ $o->service->type->label() }}</td>
          <td>{{ $o->note }}</td>
          <td class="text-end">{{ number_format($o->price, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><th colspan="4" class="text-end">Tổng cộng</th><th class="text-end">{{ number_format($orders->sum('price'), 0, ',', '.') }}</th></tr>
    </tfoot>
  </table>

  <div class="row">
    <div class="col-6 small"><em>Bệnh nhân mang phiếu đến phòng xét nghiệm / chẩn đoán hình ảnh.</em></div>
    <div class="col-6 sign-box">
      <div><em>Ngày {{ $orders->first()->created_at->format('d') }} tháng {{ $orders->first()->created_at->format('m') }} năm {{ $orders->first()->created_at->format('Y') }}</em></div>
      <div class="fw-bold">Bác sĩ chỉ định</div>
      <div style="height: 55px"></div>
      <div class="fw-bold">{{ $visit->doctor->display_name }}</div>
    </div>
  </div>
@endsection
