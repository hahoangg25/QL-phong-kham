@extends('client.layouts.account')

@section('title', 'Hóa đơn')
@section('page_title', 'Hóa đơn & thanh toán')
@section('breadcrumbs')
  <li><a href="{{ route('account.dashboard') }}">Tài khoản</a></li>
@endsection

@section('account_title', 'Hóa đơn')

@section('account_content')
  <div class="card card-soft mb-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small">
          <tr><th>Mã hóa đơn</th><th>Ngày</th><th>Bệnh nhân</th><th>Bác sĩ</th><th class="text-end">Tổng tiền</th><th>Trạng thái</th><th></th></tr>
        </thead>
        <tbody>
          @forelse ($invoices as $inv)
            <tr>
              <td class="fw-semibold">{{ $inv->code }}</td>
              <td class="small">{{ $inv->created_at->format('d/m/Y') }}</td>
              <td class="small">{{ $inv->patient->full_name }}</td>
              <td class="small">{{ $inv->visit?->doctor?->display_name ?? '-' }}</td>
              <td class="text-end fw-semibold">{{ money($inv->total) }}</td>
              <td>{!! status_badge($inv->status) !!}</td>
              <td class="text-end text-nowrap">
                <a href="{{ route('account.invoices.show', $inv) }}" class="btn btn-sm btn-outline-primary">Xem</a>
                <a href="{{ route('account.invoices.pdf', $inv) }}" class="btn btn-sm btn-outline-secondary" title="Tải PDF"><i class="bi bi-file-earmark-pdf"></i></a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-receipt fs-1 d-block mb-2"></i>Chưa có hóa đơn nào.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($invoices->hasPages())
      <div class="card-footer bg-transparent">{{ $invoices->links() }}</div>
    @endif
  </div>

  @if ($deposits->isNotEmpty())
    <div class="card card-soft">
      <div class="card-header"><i class="bi bi-wallet2 me-1"></i>Thanh toán trước cho lịch hẹn (gần đây)</div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
          <thead class="table-light"><tr><th>Mã giao dịch</th><th>Lịch hẹn</th><th>Thời gian</th><th class="text-end">Số tiền</th><th>Trạng thái</th></tr></thead>
          <tbody>
            @foreach ($deposits as $p)
              <tr>
                <td>{{ $p->code }}</td>
                <td>@if ($p->appointment)<a href="{{ route('account.appointments.show', $p->appointment) }}">{{ $p->appointment->code }}</a>@endif</td>
                <td>{{ ($p->paid_at ?? $p->created_at)->format('H:i d/m/Y') }}</td>
                <td class="text-end">{{ money($p->amount) }}</td>
                <td>{!! status_badge($p->status) !!}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endsection
