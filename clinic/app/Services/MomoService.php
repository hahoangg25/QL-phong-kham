<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Billing\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Tích hợp cổng ví MoMo (môi trường test) - API thanh toán v2 (AIO, captureWallet).
 * Luồng: tạo giao dịch -> nhận payUrl -> người dùng thanh toán trên MoMo
 *        -> MoMo gọi IPN (POST JSON, server-to-server) và chuyển trình duyệt về redirectUrl.
 * Mọi dữ liệu MoMo trả về đều được xác thực chữ ký HMAC-SHA256 bằng secretKey.
 * Khi chưa cấu hình MOMO_PARTNER_CODE/MOMO_ACCESS_KEY/MOMO_SECRET_KEY, hệ thống dùng cổng giả lập.
 */
class MomoService
{
    /** Thứ tự trường ký khi tạo giao dịch (theo tài liệu MoMo). */
    protected const CREATE_FIELDS = ['accessKey', 'amount', 'extraData', 'ipnUrl', 'orderId', 'orderInfo', 'partnerCode', 'redirectUrl', 'requestId', 'requestType'];

    /** Thứ tự trường ký trong kết quả trả về (IPN và redirect). */
    protected const RESULT_FIELDS = ['accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'];

    public function isConfigured(): bool
    {
        return filled(config('clinic.momo.partner_code'))
            && filled(config('clinic.momo.access_key'))
            && filled(config('clinic.momo.secret_key'));
    }

    /**
     * Gọi API tạo giao dịch và trả về đường dẫn thanh toán (payUrl) của MoMo.
     *
     * @throws BusinessRuleException khi MoMo từ chối hoặc không phản hồi.
     */
    public function createPaymentUrl(Payment $payment, string $redirectUrl, string $ipnUrl, string $orderInfo): string
    {
        $params = [
            'partnerCode' => config('clinic.momo.partner_code'),
            'accessKey' => config('clinic.momo.access_key'),
            'requestId' => $payment->code.'-'.Str::lower(Str::random(6)),
            'amount' => (string) $payment->amount,
            'orderId' => $payment->code,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'extraData' => '',
            'requestType' => config('clinic.momo.request_type', 'captureWallet'),
        ];

        $body = [
            'partnerCode' => $params['partnerCode'],
            'partnerName' => config('clinic.name'),
            'storeId' => $params['partnerCode'],
            'requestId' => $params['requestId'],
            'amount' => (int) $params['amount'],
            'orderId' => $params['orderId'],
            'orderInfo' => $params['orderInfo'],
            'redirectUrl' => $params['redirectUrl'],
            'ipnUrl' => $params['ipnUrl'],
            'lang' => 'vi',
            'extraData' => $params['extraData'],
            'requestType' => $params['requestType'],
            'signature' => $this->sign($params, self::CREATE_FIELDS),
        ];

        try {
            $response = Http::timeout(30)->acceptJson()->post(config('clinic.momo.endpoint'), $body)->json() ?? [];
        } catch (\Throwable $e) {
            report($e);

            throw new BusinessRuleException('Không kết nối được cổng thanh toán MoMo. Vui lòng thử lại sau.');
        }

        if ((int) ($response['resultCode'] ?? -1) !== 0 || empty($response['payUrl'])) {
            throw new BusinessRuleException('MoMo từ chối tạo giao dịch: '.($response['message'] ?? 'không rõ lý do').'.');
        }

        return $response['payUrl'];
    }

    /** Xác thực chữ ký dữ liệu MoMo trả về (redirect và IPN). */
    public function verify(array $input): bool
    {
        $signature = (string) ($input['signature'] ?? '');
        if ($signature === '' || ($input['partnerCode'] ?? null) !== config('clinic.momo.partner_code')) {
            return false;
        }

        $data = $input;
        $data['accessKey'] = config('clinic.momo.access_key');

        return hash_equals($this->sign($data, self::RESULT_FIELDS), strtolower($signature));
    }

    public function isSuccess(array $input): bool
    {
        return isset($input['resultCode']) && (int) $input['resultCode'] === 0;
    }

    /** Chuỗi ký dạng key1=value1&key2=value2... theo đúng thứ tự trường, băm HMAC-SHA256. */
    public function sign(array $data, array $fields): string
    {
        $raw = collect($fields)->map(fn ($f) => $f.'='.($data[$f] ?? ''))->implode('&');

        return hash_hmac('sha256', $raw, (string) config('clinic.momo.secret_key'));
    }

    public function resultFields(): array
    {
        return self::RESULT_FIELDS;
    }
}
