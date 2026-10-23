@extends('client.layouts.site')

@section('title', 'Kết quả thanh toán')
@section('page_title', 'Kết quả thanh toán')

@section('content')
  <section class="section pt-4">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
          <div class="card card-soft text-center">
            <div class="card-body p-4 p-md-5">
              <div class="mb-3">
                <i class="bi {{ $success ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}" style="font-size: 64px"></i>
              </div>
              <h4 class="mb-2">{{ $success ? 'Thanh toán thành công' : 'Thanh toán không thành công' }}</h4>
              <p class="text-muted">{{ $message }}</p>
              @if ($payment)
                <dl class="row small text-start mx-auto" style="max-width: 320px">
                  <dt class="col-6 text-muted fw-normal">Mã giao dịch</dt><dd class="col-6 fw-semibold">{{ $payment->code }}</dd>
                  <dt class="col-6 text-muted fw-normal">Số tiền</dt><dd class="col-6 fw-semibold">{{ money($payment->amount) }}</dd>
                  <dt class="col-6 text-muted fw-normal">Trạng thái</dt><dd class="col-6">{!! status_badge($payment->status) !!}</dd>
                  @if ($payment->transaction_no)
                    <dt class="col-6 text-muted fw-normal">Mã GD cổng</dt><dd class="col-6">{{ $payment->transaction_no }}</dd>
                  @endif
                </dl>
              @endif
              <a href="{{ $backUrl }}" class="btn btn-primary mt-2">
                <i class="bi bi-arrow-left me-1"></i>{{ $payment?->appointment_id ? 'Về lịch hẹn' : ($payment?->invoice_id ? 'Về hóa đơn' : 'Về tài khoản') }}
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
