# Đồ án: Hệ thống quản lý phòng khám và đặt lịch khám trực tuyến

| Thư mục / tệp | Nội dung |
|---|---|
| `Phân tích nghiệp vụ.docx` | Bối cảnh, tác nhân, quy trình, vòng đời lịch hẹn, quy tắc BR-01..BR-11, thực thể dữ liệu |
| `Danh sách chức năng.docx` | 88 chức năng (52 bắt buộc) theo 7 phân hệ |
| `Kế hoạch thực hiện.docx` | Công nghệ đề xuất (Laravel), lộ trình 19 tuần, rủi ro |
| `template/` | Template giao diện Clinic (BootstrapMade) gốc |
| `clinic/` | **Mã nguồn hệ thống** (Laravel 12) – xem [clinic/README.md](clinic/README.md) để cài đặt và chạy |
| `clinic/docs/thiet-ke-he-thong.md` | Kiến trúc, ERD, sơ đồ trạng thái, sơ đồ tuần tự, ma trận phân quyền |

Chạy nhanh (đã có XAMPP với PHP 8.2 và MySQL):

```bash
cd clinic
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Mở http://127.0.0.1:8000 và đăng nhập bằng tài khoản demo (mật khẩu `password`): `benhnhan@phongkham.test`, `admin@phongkham.test`, `letan@phongkham.test`, `bs.quang@phongkham.test`, `ktv@phongkham.test`, `thungan@phongkham.test`, `duocsi@phongkham.test`.
