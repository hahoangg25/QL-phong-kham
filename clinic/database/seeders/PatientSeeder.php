<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Clinical\Patient;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('vi_VN');
        $faker->seed(2026);

        // Tài khoản bệnh nhân demo quản lý 3 hồ sơ (bản thân, mẹ, con) - BN-04
        $demo = $this->account('Nguyễn Văn An', 'benhnhan@phongkham.test', '0901234567');
        $this->profile($demo, 'Bản thân', 'Nguyễn Văn An', '1990-05-12', Gender::Male, '0901234567', 'benhnhan@phongkham.test', 'Penicillin', 'Viêm dạ dày (2022)');
        $this->profile($demo, 'Mẹ', 'Lê Thị Bình', '1958-09-20', Gender::Female, '0901234568', null, null, 'Tăng huyết áp, đái tháo đường type 2');
        $this->profile($demo, 'Con', 'Nguyễn Minh Khôi', '2021-03-08', Gender::Male, '0901234567', null, null, null);

        $allergies = [null, null, null, null, 'Penicillin', 'Aspirin', 'Hải sản', 'Sulfamid', 'Ibuprofen'];
        $histories = [null, null, null, 'Tăng huyết áp', 'Đái tháo đường type 2', 'Hen phế quản', 'Viêm dạ dày', 'Gút', 'Rối loạn mỡ máu'];

        // 35 tài khoản bệnh nhân khác, một số có thêm hồ sơ người thân
        for ($i = 1; $i <= 35; $i++) {
            $gender = $faker->randomElement([Gender::Male, Gender::Female]);
            $name = $this->vnName($faker, $gender);
            $phone = '09'.$faker->unique()->numerify('########');
            $user = $this->account($name, 'benhnhan'.$i.'@phongkham.test', $phone);
            if ($i % 9 === 0) {
                $user->update(['no_show_count' => 3]); // minh họa hạn chế no-show (BR-05)
            }

            $this->profile($user, 'Bản thân', $name, $faker->dateTimeBetween('-75 years', '-18 years')->format('Y-m-d'),
                $gender, $phone, $user->email, $faker->randomElement($allergies), $faker->randomElement($histories), $faker);

            if ($i % 3 === 0) {
                $childGender = $faker->randomElement([Gender::Male, Gender::Female]);
                $this->profile($user, 'Con', $this->vnName($faker, $childGender, explode(' ', $name)[0]),
                    $faker->dateTimeBetween('-12 years', '-1 years')->format('Y-m-d'), $childGender, $phone, null, null, null, $faker);
            }
        }

        // Bệnh nhân vãng lai không có tài khoản
        for ($i = 1; $i <= 15; $i++) {
            $gender = $faker->randomElement([Gender::Male, Gender::Female]);
            Patient::create([
                'full_name' => $this->vnName($faker, $gender),
                'date_of_birth' => $faker->dateTimeBetween('-80 years', '-3 years')->format('Y-m-d'),
                'gender' => $gender,
                'phone' => '03'.$faker->unique()->numerify('########'),
                'id_number' => '0'.$faker->numerify('###########'),
                'address' => $faker->streetAddress().', '.$faker->randomElement(['Quận 1', 'Quận 3', 'Quận 5', 'Quận 10', 'Bình Thạnh', 'Gò Vấp', 'Thủ Đức']).', TP. Hồ Chí Minh',
                'medical_history' => $faker->randomElement($histories),
            ]);
        }
    }

    protected function account(string $name, string $email, string $phone): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => StaffSeeder::PASSWORD,
            'verified_at' => now(),
            'email_verified_at' => now(),
            'consent_at' => now(),
        ]);
        $user->assignRole(Role::Patient->value);

        return $user;
    }

    protected function profile(User $user, string $relationship, string $name, string $dob, Gender $gender, ?string $phone, ?string $email, ?string $allergies, ?string $history, $faker = null): Patient
    {
        return Patient::create([
            'user_id' => $user->id,
            'relationship' => $relationship,
            'full_name' => $name,
            'date_of_birth' => $dob,
            'gender' => $gender,
            'phone' => $phone,
            'email' => $email,
            'id_number' => $relationship === 'Con' ? null : '0'.random_int(10000000000, 99999999999),
            'address' => $faker
                ? $faker->streetAddress().', '.$faker->randomElement(['Quận 1', 'Quận 3', 'Quận 5', 'Quận 7', 'Quận 10', 'Tân Bình', 'Phú Nhuận']).', TP. Hồ Chí Minh'
                : '45 Trần Hưng Đạo, Quận 5, TP. Hồ Chí Minh',
            'blood_type' => collect(['A', 'B', 'O', 'AB', null])->random(),
            'allergies' => $allergies,
            'medical_history' => $history,
        ]);
    }

    protected function vnName($faker, Gender $gender, ?string $family = null): string
    {
        $families = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý'];
        $middleM = ['Văn', 'Minh', 'Quốc', 'Đức', 'Thanh', 'Hoàng', 'Gia', 'Công', 'Hữu'];
        $middleF = ['Thị', 'Ngọc', 'Thu', 'Thanh', 'Bảo', 'Mai', 'Phương', 'Kim'];
        $givenM = ['An', 'Bình', 'Cường', 'Dũng', 'Hải', 'Hùng', 'Khang', 'Long', 'Nam', 'Phúc', 'Quân', 'Sơn', 'Tài', 'Thắng', 'Trung', 'Tuấn', 'Việt', 'Khôi'];
        $givenF = ['Anh', 'Chi', 'Dung', 'Giang', 'Hà', 'Hạnh', 'Hương', 'Lan', 'Linh', 'My', 'Ngân', 'Nhi', 'Oanh', 'Phương', 'Quyên', 'Thảo', 'Trang', 'Vy'];

        return ($family ?? $faker->randomElement($families)).' '
            .($gender === Gender::Male ? $faker->randomElement($middleM) : $faker->randomElement($middleF)).' '
            .($gender === Gender::Male ? $faker->randomElement($givenM) : $faker->randomElement($givenF));
    }
}
