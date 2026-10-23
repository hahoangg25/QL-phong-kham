@extends('client.layouts.site')

@section('title', 'Cổng thanh toán giả lập')
@section('page_title', 'Cổng thanh toán')
@section('page_subtitle', 'Môi trường thử nghiệm - không phát sinh giao dịch thật')

@section('content')
  <section class="section pt-4">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
          @php($isMomo = $payment->method === \App\Enums\PaymentMethod::Momo)
          <div class="card card-soft overflow-hidden">
            <div class="p-3 text-white d-flex align-items-center gap-2" style="background: {{ $isMomo ? 'linear-gradient(135deg, #a50064, #d82d8b)' : 'linear-gradient(135deg, #005baa, #ed1c24)' }}">
              <i class="bi {{ $isMomo ? 'bi-wallet2' : 'bi-credit-card-2-front' }} fs-3"></i>
              <div>
                <div class="fw-bold">Cổng thanh toán giả lập - {{ $isMomo ? 'Ví MoMo' : 'VNPay' }}</div>
                <div class="small opacity-75">{{ $isMomo ? 'Dùng khi chưa cấu hình MoMo test (MOMO_PARTNER_CODE)' : 'Dùng khi chưa cấu hình VNPay sandbox (VNPAY_TMN_CODE)' }}</div>
              </div>
            </div>
            <div class="card-body p-4">
              <div class="text-center mb-3">
                <div class="text-muted small">Số tiền thanh toán</div>
                <div class="display-6 fw-bold text-primary">{{ money($payment->amount) }}</div>
              </div>
              <dl class="row small mb-4">
                <dt class="col-5 text-muted fw-normal">Mã giao dịch</dt><dd class="col-7 fw-semibold">{{ $payment->code }}</dd>
                <dt class="col-5 text-muted fw-normal">Nội dung</dt><dd class="col-7">{{ $payment->note ?? $payment->typeLabel() }}</dd>
                <dt class="col-5 text-muted fw-normal">Bệnh nhân</dt><dd class="col-7">{{ $payment->patient->full_name }}</dd>
                <dt class="col-5 text-muted fw-normal">Đơn vị nhận</dt><dd class="col-7">{{ config('clinic.name') }}</dd>
              </dl>
              <form method="POST" action="{{ route('payment.simulate.process', $payment) }}" class="d-grid gap-2">
                @csrf
                <button name="action" value="success" class="btn btn-success py-2"><i class="bi bi-check-circle me-1"></i>Thanh toán thành công</button>
                <button name="action" value="cancel" class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Hủy giao dịch</button>
              </form>
              <p class="text-muted text-center mt-3 mb-0" style="font-size:12px">
                @if ($isMomo)
                  Trong môi trường thật, hệ thống chuyển sang ví MoMo và nhận kết quả qua IPN có xác thực chữ ký HMAC-SHA256.
                @else
                  Trong môi trường thật, hệ thống chuyển sang cổng VNPay và nhận kết quả qua IPN có xác thực chữ ký HMAC-SHA512.
                @endif
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
