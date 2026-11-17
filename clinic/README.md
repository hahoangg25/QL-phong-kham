# Hệ thống quản lý phòng khám và đặt lịch khám trực tuyến

Đồ án xây dựng theo 3 tài liệu: *Phân tích nghiệp vụ*, *Danh sách chức năng* (88 chức năng, 52 bắt buộc) và *Kế hoạch thực hiện*. Giao diện cổng bệnh nhân dựng từ template **Clinic** (BootstrapMade) trong thư mục `../template`.

- **Cổng bệnh nhân**: tra cứu chuyên khoa, bác sĩ, bảng giá; đăng ký/đăng nhập bằng OTP; quản lý hồ sơ người thân; đặt, hủy, đổi lịch có giữ chỗ và xác thực OTP; mã QR check-in; theo dõi số thứ tự; lịch sử khám, đơn thuốc, kết quả CLS; hóa đơn PDF; thanh toán online VNPay hoặc ví MoMo (hoặc cổng giả lập); thông báo; đánh giá bác sĩ.
- **Hệ thống nội bộ**: lễ tân (lịch hẹn, xác nhận, check-in QR, vãng lai, hàng đợi realtime, phiếu số thứ tự), bác sĩ (hàng đợi, bệnh án điện tử, ICD-10, chỉ định CLS, kê đơn có cảnh báo tồn kho và dị ứng, khóa bệnh án), kỹ thuật viên CLS, thu ngân, dược và kho (FEFO), quản trị (danh mục, ca làm việc, sinh slot, duyệt nghỉ, RBAC, cấu hình quy tắc, báo cáo, nhật ký).
- **Tự động**: giữ slot tạm, chống đặt trùng, nhắc lịch 24h/2h, đánh dấu vắng mặt, hạn chế no-show, hàng đợi WebSocket, xử lý IPN thanh toán, audit log, sao lưu CSDL.

## 1. Công nghệ

| Thành phần | Sử dụng |
|---|---|
| Backend | Laravel 12, PHP 8.2+, kiến trúc MVC + Service |
| Giao diện | Blade + Bootstrap 5 (template Clinic), JavaScript thuần |
| CSDL | MySQL 8 / MariaDB 10.4+ (XAMPP) |
| Phân quyền, audit | spatie/laravel-permission, spatie/laravel-activitylog |
| Thời gian thực | Laravel Reverb (WebSocket, giao thức Pusher) + polling dự phòng |
| Tác vụ nền | Laravel Scheduler + Queue (database) |
| Khác | barryvdh/laravel-dompdf (PDF), bacon/bacon-qr-code (QR SVG), Chart.js (biểu đồ) |
| Kiểm thử | PHPUnit (SQLite in-memory), lệnh `clinic:race-test` kiểm thử tranh chấp trên MySQL |

Không cần Redis hay extension `gd`: cache/queue/khóa dùng bảng database, QR xuất dạng SVG.

## 2. Cài đặt (Windows + XAMPP)

Yêu cầu: PHP ≥ 8.2 (bật `pdo_mysql`, `mbstring`, `intl`, `zip`, `fileinfo`), Composer, MySQL/MariaDB.

```bash
cd clinic
composer install
copy .env.example .env        # Linux/macOS: cp .env.example .env
php artisan key:generate
```

Tạo CSDL `phong_kham` (utf8mb4) trong phpMyAdmin hoặc:

```bash
mysql -uroot -e "CREATE DATABASE phong_kham CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Kiểm tra thông số `DB_*` trong `.env`, rồi tạo bảng và dữ liệu mẫu (khoảng 2 phút):

```bash
php artisan migrate:fresh --seed
```

Dữ liệu mẫu: 10 chuyên khoa, 12 bác sĩ có lịch làm việc, 35 dịch vụ, 40 thuốc nhiều lô, 115 mã ICD-10, ~50 bệnh nhân, slot từ 30 ngày trước đến 14 ngày tới, 30 ngày lịch sử khám (lịch hẹn, bệnh án, đơn thuốc, hóa đơn, đánh giá) và hàng đợi của ngày hôm nay.

> **Lưu ý:** dị ứng và tiền sử bệnh được mã hóa bằng `APP_KEY`. Đổi `APP_KEY` sau khi có dữ liệu sẽ không đọc lại được các trường này.

## 3. Chạy hệ thống

```bash
php artisan serve
```

Mở http://127.0.0.1:8000. Các tiến trình tùy chọn (mỗi lệnh một cửa sổ):

```bash
php artisan schedule:work
```

Chạy tác vụ định kỳ: dọn chỗ giữ hết hạn (mỗi phút), nhắc lịch (10 phút), đánh dấu vắng mặt (5 phút), sinh slot (00:30), sao lưu (02:00).

```bash
php artisan reverb:start
```

Hàng đợi realtime qua WebSocket: đặt `BROADCAST_CONNECTION=reverb` trong `.env`. Nếu không chạy Reverb, các màn hình vẫn tự làm mới bằng polling.

```bash
php artisan queue:work
```

Chỉ cần khi đổi `QUEUE_CONNECTION=database` để gửi email/thông báo nền (mặc định `sync`).

### Tài khoản demo

Mật khẩu chung: `password`.

| Vai trò | Email |
|---|---|
| Bệnh nhân (3 hồ sơ: bản thân, mẹ, con) | benhnhan@phongkham.test (SĐT 0901234567) |
| Quản trị viên | admin@phongkham.test |
| Lễ tân | letan@phongkham.test, letan2@phongkham.test |
| Bác sĩ | bs.quang@phongkham.test, bs.ha, bs.nhung, bs.huy, bs.thinh, bs.mai, bs.nam, bs.linh, bs.tam, bs.bao, bs.long, bs.trang (@phongkham.test) |
| Kỹ thuật viên CLS | ktv@phongkham.test |
| Thu ngân | thungan@phongkham.test |
| Dược sĩ | duocsi@phongkham.test |

Bệnh nhân khác: benhnhan1 … benhnhan35@phongkham.test (benhnhan9, 18, 27 đã vắng mặt 3 lần để minh họa BR-05).

### OTP và email khi demo

Mặc định `MAIL_MAILER=log`: email ghi vào `storage/logs/laravel.log`, SMS giả lập ghi vào `storage/logs/sms.log`. Khi `CLINIC_SHOW_OTP=true`, mã OTP hiện ngay trên màn hình để demo. Khi triển khai thật, cấu hình SMTP và đặt `CLINIC_SHOW_OTP=false`.

### Thanh toán VNPay sandbox

Đăng ký tài khoản test tại https://sandbox.vnpayment.vn/devreg/ rồi điền vào `.env`:

```
VNPAY_TMN_CODE=xxxxxxxx
VNPAY_HASH_SECRET=xxxxxxxxxxxxxxxx
```

IPN URL cấu hình trên cổng VNPay: `https://<domain>/thanh-toan/vnpay/ipn` (khi chạy local dùng ngrok). Để trống hai biến trên thì hệ thống dùng **cổng thanh toán giả lập** để demo luồng đặt cọc/trả trước.

### Thanh toán ví MoMo (môi trường test)

Đăng ký tài khoản doanh nghiệp test tại https://business.momo.vn (hoặc dùng thông tin test trong tài liệu https://developers.momo.vn) rồi điền vào `.env`:

```
MOMO_PARTNER_CODE=xxxxxxxx
MOMO_ACCESS_KEY=xxxxxxxxxxxxxxxx
MOMO_SECRET_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MOMO_ENDPOINT=https://test-payment.momo.vn/v2/gateway/api/create
```

Bệnh nhân chọn nút **Thanh toán ví MoMo** ở chi tiết lịch hẹn hoặc hóa đơn. Hệ thống gọi API tạo giao dịch (chữ ký HMAC-SHA256), chuyển người dùng sang trang MoMo; kết quả về qua `/thanh-toan/momo/return` và IPN `POST /thanh-toan/momo/ipn` (miễn CSRF, xác thực chữ ký, phản hồi HTTP 204). Khi chạy local, IPN cần URL công khai (ngrok). Để trống các khóa thì nút MoMo dùng **cổng thanh toán giả lập**.

## 4. Kiểm thử

```bash
php artisan test
```

Chạy toàn bộ feature test trên SQLite in-memory (không đụng dữ liệu MySQL). `tests/Feature/BusinessRulesTest.php` kiểm chứng trực tiếp BR-01 → BR-11, HT-01, HT-04 và FEFO.

```bash
php artisan clinic:race-test --n=20
```

Kiểm thử tranh chấp slot trên MySQL thật: 20 tiến trình PHP cùng đặt một khung giờ tại cùng một thời điểm; kết quả mong đợi là số lịch thành công đúng bằng sức chứa của slot (tự dọn dữ liệu thử sau khi chạy).

## 5. Quy tắc nghiệp vụ được cài đặt ở đâu

| Mã | Quy tắc | Cài đặt |
|---|---|---|
| BR-01 | Không vượt sức chứa slot, xử lý đặt đồng thời | `BookingService` – `DB::transaction` + `lockForUpdate()` trên `time_slots`, đếm cả chỗ đang giữ |
| BR-02 | Đặt trước tối thiểu X giờ, tối đa Y ngày | `BookingService::assertBookingWindow()` |
| BR-03 | Một lịch hoạt động/chuyên khoa/ngày cho mỗi hồ sơ | `BookingService::assertNoActiveSameDay()` (khóa dòng hồ sơ bệnh nhân) |
| BR-04 | Hủy trễ tính no-show | `BookingService::cancel()`, `Appointment::isLateCancellationNow()` |
| BR-05 | Vượt ngưỡng no-show: khóa đặt online hoặc bắt buộc cọc | `User::canBookOnline()`, `User::requiredDeposit()`, lệnh `clinic:mark-no-shows` |
| BR-06 | Check-in trễ quá 15 phút xuống cuối hàng | `QueueService::checkIn()` (khóa sắp xếp `queue_sort`) |
| BR-07 | Chỉ bác sĩ phụ trách ghi bệnh án | `ExaminationService::assertEditable()` |
| BR-08 | Bệnh án hoàn thành chỉ được bổ sung, mọi truy cập ghi log | `visits.locked_at`, `visit_addenda`, `App\Support\Audit::viewed()` |
| BR-09 | Bệnh nhân chỉ xem hồ sơ của mình và người thân | Kiểm tra `patient.user_id` trong các controller `Account` |
| BR-10 | Cảnh báo tồn kho/dị ứng; đơn đã cấp không sửa | `ExaminationService::prescriptionWarnings()`, `Prescription::isEditable()`, `PharmacyService::dispense()` |
| BR-11 | Đồng ý xử lý dữ liệu, mã hóa dữ liệu nhạy cảm, phân quyền | `users.consent_at`, cast `encrypted` trên `patients`, RBAC |

Tham số X, Y, K, N chỉnh trong **Quản trị → Cấu hình quy tắc** (QT-11); mặc định ở `config/clinic.php`.

## 6. Cấu trúc mã nguồn

```
app/
  Console/Commands/     Tác vụ tự động + clinic:race-test
  Enums/                Trạng thái (AppointmentStatus 9 trạng thái, VisitStatus, ...) và vai trò
  Events/QueueUpdated   Sự kiện hàng đợi realtime (Reverb)
  Http/Controllers/
    Client/Site/        [Người dùng] Trang công khai, API slot trống, gợi ý chuyên khoa, màn hình sảnh
    Client/Account/     [Người dùng] Cổng bệnh nhân: đặt lịch, hồ sơ, hóa đơn, thông báo
    Auth/, PaymentGatewayController   Dùng chung: đăng nhập/đăng ký OTP, cổng thanh toán
    Admin/              [Nội bộ] Dashboard, thông báo, hồ sơ cá nhân của nhân viên
    Admin/Reception/    Lễ tân: lịch hẹn, check-in, hàng đợi, bệnh nhân
    Admin/Doctor/       Bác sĩ: lịch làm việc, khám bệnh, bệnh án, nghỉ phép
    Admin/Lab/          Kỹ thuật viên cận lâm sàng
    Admin/Cashier/      Thu ngân
    Admin/Pharmacy/     Dược: cấp thuốc, danh mục thuốc, nhập kho, tồn kho
    Admin/Management/   Quản trị: danh mục, ca làm, phân quyền, cấu hình, báo cáo, nhật ký
  Models/               Eloquent models, chia theo nhóm nghiệp vụ (ánh xạ thực thể ở docs/thiet-ke-he-thong.md)
    (gốc)               User, Setting, OtpCode
    Catalog/            Specialty, Doctor, Room, Service, Icd10Code
    Scheduling/         WorkShift, LeaveRequest, TimeSlot, SlotHold, Appointment, AppointmentStatusLog
    Clinical/           Patient, Visit, Diagnosis, ServiceOrder, LabResult, Prescription, Review, ...
    Pharmacy/           Medicine, MedicineBatch, StockReceipt, StockTransaction
    Billing/            Invoice, InvoiceItem, Payment
  Notifications/        Thông báo email / SMS giả lập / trong ứng dụng
  Services/             Nghiệp vụ: Booking, Queue, Examination, Billing, Pharmacy, Leave, Slot, OTP, VNPay, MoMo
  Support/              Settings (tham số BR), Permissions (RBAC), QrCode, Audit
config/clinic.php       Thông tin phòng khám, tham số quy tắc mặc định, VNPay, MoMo
database/migrations     Lược đồ CSDL
database/seeders        Dữ liệu mẫu (HistorySeeder sinh 30 ngày vận hành)
docs/                   Thiết kế hệ thống: kiến trúc, ERD, sơ đồ trạng thái, tuần tự
resources/views/        client/ (site, account, layouts, partials), admin/ (cùng nhóm với Controllers/Admin, layouts, partials), common/ (auth, payments, layout in, flash)
tests/Feature/          Kiểm thử chức năng và quy tắc nghiệp vụ
```

## 7. Triển khai

Trên VPS (Nginx + PHP-FPM): `composer install --no-dev -o`, `php artisan migrate --force`, `php artisan config:cache route:cache view:cache`; cron `* * * * * php /path/artisan schedule:run`; Supervisor cho `php artisan queue:work` và `php artisan reverb:start`; bật HTTPS. Đặt `APP_ENV=production`, `APP_DEBUG=false`, `CLINIC_SHOW_OTP=false`.

## 8. Bản quyền template

Giao diện cổng bệnh nhân dùng template Clinic của BootstrapMade (https://bootstrapmade.com/license/), giữ nguyên dòng credit ở chân trang.
