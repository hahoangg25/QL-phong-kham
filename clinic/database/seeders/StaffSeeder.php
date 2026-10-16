<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Catalog\Doctor;
use App\Models\Catalog\Room;
use App\Models\Catalog\Specialty;
use App\Models\User;
use App\Models\Scheduling\WorkShift;
use Illuminate\Database\Seeder;

/**
 * Tài khoản nhân viên demo. Mật khẩu chung: password
 */
class StaffSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /** [slug chuyên khoa, học hàm/học vị, họ tên, email, ảnh, phí khám, năm KN, chức vụ] */
    public const DOCTORS = [
        ['cay-ghep-implant', 'PGS.TS.BS', 'Trần Minh Quang', 'bs.quang', 'staff-1.webp', 350000, 25, 'Giám đốc chuyên môn - Chuyên gia Cấy ghép Implant'],
        ['nha-khoa-tong-quat', 'ThS.BS', 'Đỗ Quốc Bảo', 'bs.bao', 'staff-14.webp', 150000, 12, 'Trưởng khoa Nha khoa Tổng quát & Điều trị'],
        ['chinh-nha-nieng-rang', 'BSCKII', 'Lê Thị Hồng Nhung', 'bs.nhung', 'staff-4.webp', 250000, 18, 'Trưởng đơn nguyên Chỉnh nha & Niềng răng thẩm mỹ'],
        ['noi-nha-chua-tuy', 'ThS.BS', 'Nguyễn Thị Thu Hà', 'bs.ha', 'staff-2.webp', 200000, 11, 'Phụ trách Khoa Nội nha & Điều trị tủy'],
        ['tieu-phau-rang-khon', 'TS.BS', 'Võ Đức Thịnh', 'bs.thinh', 'staff-5.webp', 250000, 20, 'Trưởng khoa Tiểu phẫu & Phẫu thuật Răng khôn'],
        ['rang-su-tham-my', 'BSCKII', 'Hoàng Văn Nam', 'bs.nam', 'staff-7.webp', 250000, 22, 'Chuyên gia Phục hình Răng sứ & Thẩm mỹ nụ cười'],
    ];

    public function run(): void
    {
        $this->staff('Quản trị hệ thống', 'admin@phongkham.test', '0900000001', 'NV001', Role::Admin);
        $this->staff('Nguyễn Thị Hoa', 'letan@phongkham.test', '0900000002', 'NV002', Role::Receptionist);
        $this->staff('Trần Văn Tùng', 'letan2@phongkham.test', '0900000003', 'NV003', Role::Receptionist);

        $specialties = Specialty::pluck('id', 'slug');
        $usedRooms = [];

        foreach (self::DOCTORS as $i => [$slug, $title, $name, $email, $image, $fee, $years, $position]) {
            $user = $this->staff($name, $email.'@phongkham.test', '09100000'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'BS'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), Role::Doctor);

            $room = Room::where('specialty_id', $specialties[$slug])->whereNotIn('id', $usedRooms)->orderBy('code')->first();
            if (! $room) {
                $room = Room::where('specialty_id', $specialties[$slug])->first();
            } else {
                $usedRooms[] = $room->id;
            }

            $spec = Specialty::find($specialties[$slug]);
            $doctor = Doctor::create([
                'user_id' => $user->id,
                'specialty_id' => $specialties[$slug],
                'room_id' => $room->id,
                'title' => $title,
                'position' => $position,
                'experience_years' => $years,
                'consultation_fee' => $fee,
                'avatar' => $image ? 'assets/img/health/'.$image : null,
                'short_bio' => "{$years} năm kinh nghiệm chuyên sâu trong lĩnh vực {$spec->name}.",
                'bio' => "{$title} {$name} tốt nghiệp Đại học Y Dược, có {$years} năm kinh nghiệm thực hành và điều trị chuyên sâu Răng Hàm Mặt. "
                    ."Hiện là {$position} tại Nha khoa Quốc tế Lộc Phát. Bác sĩ thao tác nhẹ nhàng, không đau, tận tâm mang lại hàm răng khỏe mạnh và nụ cười rạng rỡ cho khách hàng.\n\n"
                    ."Quá trình công tác:\n- Bác sĩ chuyên khoa Răng Hàm Mặt tại Bệnh viện tuyến Trung ương\n- Thành viên Hội Răng Hàm Mặt Việt Nam (VOSA)\n"
                    .'- Tu nghiệp chuyên sâu về Chỉnh nha & Implant tại Hoa Kỳ và Hàn Quốc.',
            ]);

            $this->shifts($doctor, $i, $slug);
        }
    }

    protected function staff(string $name, string $email, string $phone, string $code, Role $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'employee_code' => $code,
            'password' => self::PASSWORD,
            'verified_at' => now(),
            'email_verified_at' => now(),
            'consent_at' => now(),
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    /** Ca sáng thứ 2 - thứ 7, ca chiều xen kẽ; mỗi slot 30 phút. */
    protected function shifts(Doctor $doctor, int $index, string $slug): void
    {
        $capacity = in_array($slug, ['nha-khoa-tong-quat', 'chinh-nha-nieng-rang'], true) ? 3 : 2;
        $afternoons = $index % 2 === 0 ? [1, 3, 5] : [2, 4];

        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            WorkShift::create([
                'doctor_id' => $doctor->id,
                'room_id' => $doctor->room_id,
                'weekday' => $weekday,
                'start_time' => '07:30',
                'end_time' => '11:30',
                'slot_minutes' => 30,
                'max_patients_per_slot' => $capacity,
            ]);
        }

        foreach ($afternoons as $weekday) {
            WorkShift::create([
                'doctor_id' => $doctor->id,
                'room_id' => $doctor->room_id,
                'weekday' => $weekday,
                'start_time' => '13:30',
                'end_time' => '16:30',
                'slot_minutes' => 30,
                'max_patients_per_slot' => $capacity,
            ]);
        }
    }
}
