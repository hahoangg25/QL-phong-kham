@extends('common.layouts.print')

@section('title', 'Phiếu khám STT '.$visit->queue_number)
@section('sheet_class', 'a5')
@section('head_right')
  Mã lượt khám<br><strong>{{ $visit->code }}</strong>
@endsection

@section('content')
  <div class="doc-title">Phiếu số thứ tự khám bệnh</div>
  <div class="text-center my-2">
    <div style="font-size: 15px">Số thứ tự</div>
    <div style="font-size: 72px; font-weight: 800; line-height: 1">{{ $visit->queue_number }}</div>
    @if ($visit->is_priority)<div class="fw-bold">(ƯU TIÊN)</div>@endif
  </div>

  <table class="w-100 mt-3" style="font-size: 15px">
    <tr><td style="width: 38%">Bệnh nhân:</td><td><strong>{{ $visit->patient->full_name }}</strong> ({{ $visit->patient->code }})</td></tr>
    <tr><td>Năm sinh / giới:</td><td>{{ $visit->patient->date_of_birth?->format('Y') ?? '-' }} · {{ $visit->patient->genderLabel() ?: '-' }}</td></tr>
    <tr><td>Chuyên khoa:</td><td>{{ $visit->specialty->name }}</td></tr>
    <tr><td>Bác sĩ:</td><td><strong>{{ $visit->doctor->display_name }}</strong></td></tr>
    <tr><td>Phòng khám:</td><td><strong>{{ ($visit->room ?? $visit->doctor->room)?->code ?? '-' }}</strong> {{ ($visit->room ?? $visit->doctor->room)?->floor ? '- Tầng '.($visit->room ?? $visit->doctor->room)->floor : '' }}</td></tr>
    <tr><td>Giờ tiếp nhận:</td><td>{{ $visit->checked_in_at?->format('H:i d/m/Y') }}</td></tr>
    @if ($visit->appointment)
      <tr><td>Lịch hẹn:</td><td>{{ $visit->appointment->code }} - {{ $visit->appointment->scheduled_at->format('H:i') }}</td></tr>
    @endif
    <tr><td>Số người chờ trước:</td><td>{{ $ahead }}</td></tr>
  </table>

  <p class="text-center mt-3 mb-0 fst-italic">Vui lòng chờ gọi số tại khu vực phòng khám. Xin cảm ơn!</p>
@endsection
