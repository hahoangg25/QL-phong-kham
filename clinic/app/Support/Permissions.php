<?php

namespace App\Support;

use App\Enums\Role;

/** Danh sách quyền (RBAC - QT-02) và phân quyền mặc định theo vai trò. */
class Permissions
{
    public const GROUPS = [
        'Lễ tân' => [
            'appointments.view' => 'Xem danh sách lịch hẹn',
            'appointments.manage' => 'Xác nhận, đặt/hủy/đổi lịch hộ',
            'reception.manage' => 'Check-in, tiếp nhận vãng lai, điều phối hàng đợi',
            'patients.view' => 'Tra cứu bệnh nhân',
            'patients.manage' => 'Thêm/sửa thông tin hành chính bệnh nhân',
        ],
        'Bác sĩ' => [
            'medical.examine' => 'Khám bệnh, ghi bệnh án, kê đơn',
            'medical.view' => 'Xem hồ sơ, tiền sử bệnh án',
            'leave.request' => 'Gửi yêu cầu nghỉ/đổi ca',
        ],
        'Cận lâm sàng' => [
            'lab.manage' => 'Thực hiện chỉ định, nhập và trả kết quả CLS',
        ],
        'Thu ngân & Dược' => [
            'billing.manage' => 'Lập hóa đơn, thu tiền, hoàn/hủy hóa đơn',
            'pharmacy.dispense' => 'Cấp phát thuốc theo đơn',
            'inventory.manage' => 'Danh mục thuốc, nhập kho, kiểm kê, báo cáo kho',
        ],
        'Quản trị' => [
            'catalog.manage' => 'Quản lý chuyên khoa, phòng, bác sĩ, dịch vụ, ICD-10',
            'schedule.manage' => 'Cấu hình ca làm, sinh slot, duyệt nghỉ',
            'staff.manage' => 'Quản lý tài khoản nhân viên và phân quyền',
            'settings.manage' => 'Cấu hình quy tắc nghiệp vụ',
            'reports.view' => 'Xem dashboard, báo cáo thống kê',
            'reviews.manage' => 'Quản lý đánh giá',
            'audit.view' => 'Xem nhật ký hệ thống',
        ],
    ];

    public const DEFAULT_ROLE_PERMISSIONS = [
        'receptionist' => ['appointments.view', 'appointments.manage', 'reception.manage', 'patients.view', 'patients.manage', 'billing.manage', 'pharmacy.dispense'],
        'doctor' => ['medical.examine', 'medical.view', 'leave.request', 'patients.view', 'appointments.view', 'lab.manage'],
    ];

    public static function all(): array
    {
        return collect(self::GROUPS)->flatMap(fn ($items) => array_keys($items))->all();
    }

    public static function label(string $permission): string
    {
        foreach (self::GROUPS as $items) {
            if (isset($items[$permission])) {
                return $items[$permission];
            }
        }

        return $permission;
    }

    public static function roles(): array
    {
        return Role::staffOptions();
    }
}
