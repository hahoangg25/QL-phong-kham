<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Billing\Payment;
use App\Services\BillingService;
use App\Services\MomoService;
use App\Services\VnpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * HT-08: xử lý kết quả thanh toán.
 * - vnpayReturn / momoReturn: trình duyệt quay về sau khi thanh toán (xác thực chữ ký, hiển thị kết quả).
 * - vnpayIpn / momoIpn: cổng thanh toán gọi server-to-server để xác nhận giao dịch (nguồn sự thật, idempotent).
 * - simulate: cổng giả lập khi chưa cấu hình VNPay/MoMo (phương án dự phòng trong kế hoạch).
 */
class PaymentGatewayController extends Controller
{
    public function __construct(
        protected VnpayService $vnpay,
        protected MomoService $momo,
        protected BillingService $billing,
    ) {}

    /** Trình duyệt quay về từ MoMo (redirectUrl, GET). */
    public function momoReturn(Request $request)
    {
        $input = $request->query();

        if (! $this->momo->verify($input)) {
            return $this->result(null, false, 'Chữ ký dữ liệu MoMo trả về không hợp lệ. Giao dịch không được ghi nhận.');
        }

        $payment = Payment::with(['appointment', 'invoice'])->where('code', $input['orderId'] ?? '')->first();
        if (! $payment) {
            return $this->result(null, false, 'Không tìm thấy giao dịch.');
        }

        $amountOk = (int) ($input['amount'] ?? 0) === (int) $payment->amount;

        if ($this->momo->isSuccess($input) && $amountOk) {
            $this->billing->completeOnlinePayment($payment, (string) ($input['transId'] ?? ''), $input);

            return $this->result($payment->refresh(), true, 'Thanh toán thành công qua ví MoMo.');
        }

        $this->billing->failOnlinePayment($payment, $input);

        return $this->result($payment->refresh(), false, 'Giao dịch MoMo không thành công hoặc đã bị hủy (mã '.($input['resultCode'] ?? '?').': '.($input['message'] ?? '').').');
    }

    /**
     * MoMo gọi IPN (POST JSON). Theo tài liệu MoMo, đối tác phản hồi HTTP 204 khi đã nhận;
     * chữ ký sai hoặc dữ liệu không khớp trả 400 để MoMo không coi là đã xác nhận.
     */
    public function momoIpn(Request $request): Response
    {
        try {
            $input = $request->json()->all() ?: $request->all();

            if (! $this->momo->verify($input)) {
                return response('Invalid signature', 400);
            }

            $payment = Payment::where('code', $input['orderId'] ?? '')->first();
            if (! $payment) {
                return response('Order not found', 404);
            }

            if ((int) ($input['amount'] ?? 0) !== (int) $payment->amount) {
                return response('Invalid amount', 400);
            }

            // Đã xử lý trước đó: vẫn trả 204 để MoMo dừng gửi lại (idempotent)
            if ($payment->status === PaymentStatus::Pending) {
                if ($this->momo->isSuccess($input)) {
                    $this->billing->completeOnlinePayment($payment, (string) ($input['transId'] ?? ''), $input);
                } else {
                    $this->billing->failOnlinePayment($payment, $input);
                }
            }

            return response()->noContent();
        } catch (\Throwable $e) {
            report($e);

            return response('Unknown error', 500);
        }
    }

    public function vnpayReturn(Request $request)
    {
        $input = $request->query();

        if (! $this->vnpay->verify($input)) {
            return $this->result(null, false, 'Chữ ký dữ liệu trả về không hợp lệ. Giao dịch không được ghi nhận.');
        }

        $payment = Payment::with(['appointment', 'invoice'])->where('code', $input['vnp_TxnRef'] ?? '')->first();
        if (! $payment) {
            return $this->result(null, false, 'Không tìm thấy giao dịch.');
        }

        $amountOk = (int) ($input['vnp_Amount'] ?? 0) === $payment->amount * 100;

        if ($this->vnpay->isSuccess($input) && $amountOk) {
            $this->billing->completeOnlinePayment($payment, $input['vnp_TransactionNo'] ?? null, $input);

            return $this->result($payment->refresh(), true, 'Thanh toán thành công qua VNPay.');
        }

        $this->billing->failOnlinePayment($payment, $input);

        return $this->result($payment->refresh(), false, 'Giao dịch không thành công hoặc đã bị hủy (mã '.($input['vnp_ResponseCode'] ?? '?').').');
    }

    public function vnpayIpn(Request $request): JsonResponse
    {
        try {
            $input = $request->query();

            if (! $this->vnpay->verify($input)) {
                return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
            }

            $payment = Payment::where('code', $input['vnp_TxnRef'] ?? '')->first();
            if (! $payment) {
                return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
            }

            if ((int) ($input['vnp_Amount'] ?? 0) !== $payment->amount * 100) {
                return response()->json(['RspCode' => '04', 'Message' => 'Invalid amount']);
            }

            if ($payment->status !== PaymentStatus::Pending) {
                return response()->json(['RspCode' => '02', 'Message' => 'Order already confirmed']);
            }

            if ($this->vnpay->isSuccess($input)) {
                $this->billing->completeOnlinePayment($payment, $input['vnp_TransactionNo'] ?? null, $input);
            } else {
                $this->billing->failOnlinePayment($payment, $input);
            }

            return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['RspCode' => '99', 'Message' => 'Unknown error']);
        }
    }

    public function simulate(Payment $payment)
    {
        $this->authorizePayment($payment);
        $payment->load(['appointment.doctor.user', 'invoice', 'patient']);

        if ($payment->status !== PaymentStatus::Pending) {
            return $this->result($payment, $payment->status === PaymentStatus::Success, 'Giao dịch đã được xử lý trước đó.');
        }

        return view('common.payments.simulate', ['payment' => $payment]);
    }

    public function simulateProcess(Request $request, Payment $payment)
    {
        $this->authorizePayment($payment);

        if ($payment->status !== PaymentStatus::Pending) {
            return $this->result($payment, $payment->status === PaymentStatus::Success, 'Giao dịch đã được xử lý trước đó.');
        }

        $gateway = $payment->method?->gatewayName() ?? 'VNPay';
        $payload = ['gateway' => 'simulator', 'method' => $payment->method?->value, 'action' => $request->input('action'), 'at' => now()->toDateTimeString()];

        if ($request->input('action') === 'success') {
            $this->billing->completeOnlinePayment($payment, 'SIM'.time(), $payload);

            return $this->result($payment->refresh(), true, 'Thanh toán thành công qua '.$gateway.' (cổng giả lập).');
        }

        $this->billing->failOnlinePayment($payment, $payload);

        return $this->result($payment->refresh(), false, 'Bạn đã hủy giao dịch.');
    }

    protected function authorizePayment(Payment $payment): void
    {
        abort_unless($payment->patient?->user_id === auth()->id(), 403);
    }

    protected function result(?Payment $payment, bool $success, string $message)
    {
        $payment?->loadMissing(['appointment', 'invoice']);

        $backUrl = match (true) {
            (bool) $payment?->appointment_id => route('account.appointments.show', $payment->appointment_id),
            (bool) $payment?->invoice_id => route('account.invoices.show', $payment->invoice_id),
            default => route('account.dashboard'),
        };

        return view('common.payments.result', compact('payment', 'success', 'message', 'backUrl'));
    }
}
