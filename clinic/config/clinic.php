<?php

/*
|--------------------------------------------------------------------------
| Cấu hình nghiệp vụ phòng khám
|--------------------------------------------------------------------------
| Các giá trị trong "rules" là mặc định cho tham số quy tắc nghiệp vụ (BR).
| Quản trị viên có thể ghi đè trong màn hình Cấu hình quy tắc (QT-11);
| giá trị ghi đè được lưu ở bảng settings và đọc qua App\Support\Settings.
*/

return [

    'name' => env('APP_NAME', 'Nha khoa Quốc tế Lộc Phát'),
    'hotline' => env('CLINIC_HOTLINE', '1900 6868'),
    'email' => env('CLINIC_EMAIL', 'lienhe@phongkham.test'),
    'address' => env('CLINIC_ADDRESS', '123 Nguyễn Văn Cừ, Quận 5, TP. Hồ Chí Minh'),
    'working_hours' => 'Thứ 2 - Thứ 7: 07:00 - 17:00',

    // Hiển thị OTP ngay trên màn hình (chỉ dùng khi demo/local)
    'show_otp' => env('CLINIC_SHOW_OTP', false),
    'otp_ttl_minutes' => 5,
    'otp_max_attempts' => 5,

    'rules' => [
        // BR-02: đặt trước giờ khám tối thiểu X giờ, không quá Y ngày
        'booking_min_hours_before' => 2,
        'booking_max_days_ahead' => 30,

        // HT-01: thời gian giữ slot trong lúc chờ OTP (phút)
        'slot_hold_minutes' => 10,

        // LT-02: tự xác nhận lịch đặt online (false = lễ tân duyệt)
        'auto_confirm_online' => false,

        // BR-04: hủy miễn phí nếu trước giờ khám X giờ
        'cancel_free_hours_before' => 4,

        // BR-05: N lần no-show thì hạn chế; chính sách: block | deposit
        'no_show_threshold' => 3,
        'no_show_policy' => 'deposit',
        'deposit_amount' => 100000,

        // HT-04: quá giờ khám bao nhiêu phút chưa check-in thì đánh dấu vắng mặt
        'no_show_after_minutes' => 60,

        // BR-06: check-in trễ quá K phút bị chuyển xuống cuối hàng đợi
        'late_checkin_minutes' => 15,

        // LT-08: tuổi được ưu tiên trong hàng đợi
        'priority_age_elderly' => 75,
        'priority_age_child' => 6,

        // QT-09: số ngày sinh slot trước
        'slot_generate_days' => 14,

        // HT-03: nhắc lịch trước X giờ
        'reminder_hours' => [24, 2],

        // TD-10: cảnh báo lô sắp hết hạn trong N ngày
        'expiry_warning_days' => 60,
    ],

    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'version' => '2.1.0',
    ],

    // Ví MoMo (môi trường test): để trống khóa thì dùng cổng thanh toán giả lập
    'momo' => [
        'partner_code' => env('MOMO_PARTNER_CODE'),
        'access_key' => env('MOMO_ACCESS_KEY'),
        'secret_key' => env('MOMO_SECRET_KEY'),
        'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
        'request_type' => env('MOMO_REQUEST_TYPE', 'captureWallet'),
    ],

    'backup' => [
        'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
        'keep' => 7,
    ],
];
