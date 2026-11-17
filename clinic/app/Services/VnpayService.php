<?php

namespace App\Services;

use App\Models\Billing\Payment;

/**
 * Tích hợp cổng VNPay sandbox (BN-10, HT-08) - phiên bản API 2.1.0.
 * Khi chưa cấu hình VNPAY_TMN_CODE/VNPAY_HASH_SECRET, hệ thống dùng cổng giả lập.
 */
class VnpayService
{
    public function isConfigured(): bool
    {
        return filled(config('clinic.vnpay.tmn_code')) && filled(config('clinic.vnpay.hash_secret'));
    }

    public function createPaymentUrl(Payment $payment, string $returnUrl, string $ipAddress, string $orderInfo): string
    {
        $params = [
            'vnp_Version' => config('clinic.vnpay.version'),
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => config('clinic.vnpay.tmn_code'),
            'vnp_Amount' => $payment->amount * 100,
            'vnp_CreateDate' => now()->format('YmdHis'),
            'vnp_ExpireDate' => now()->addMinutes(15)->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $ipAddress,
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_TxnRef' => $payment->code,
        ];

        ksort($params);
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC1738);
        $hash = hash_hmac('sha512', $query, config('clinic.vnpay.hash_secret'));

        return config('clinic.vnpay.url').'?'.$query.'&vnp_SecureHash='.$hash;
    }

    /** Xác thực chữ ký dữ liệu VNPay trả về (return URL và IPN). */
    public function verify(array $input): bool
    {
        $secureHash = $input['vnp_SecureHash'] ?? '';
        $data = collect($input)
            ->filter(fn ($v, $k) => str_starts_with($k, 'vnp_') && ! in_array($k, ['vnp_SecureHash', 'vnp_SecureHashType'], true))
            ->sortKeys()
            ->all();

        $hash = hash_hmac('sha512', http_build_query($data, '', '&', PHP_QUERY_RFC1738), (string) config('clinic.vnpay.hash_secret'));

        return $secureHash !== '' && hash_equals($hash, strtolower($secureHash));
    }

    public function isSuccess(array $input): bool
    {
        return ($input['vnp_ResponseCode'] ?? null) === '00'
            && ($input['vnp_TransactionStatus'] ?? '00') === '00';
    }
}
