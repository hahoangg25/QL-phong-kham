<?php

namespace Database\Seeders;

use App\Services\SlotGenerator;
use Illuminate\Database\Seeder;

/** Dữ liệu nền cho kiểm thử tự động: danh mục, nhân viên, thuốc, bệnh nhân, slot 14 ngày tới (không có lịch sử). */
class TestSeeder extends Seeder
{
    public function run(): void
    {
        activity()->disableLogging();

        $this->call([
            RolePermissionSeeder::class,
            CatalogSeeder::class,
            StaffSeeder::class,
            MedicineSeeder::class,
            PatientSeeder::class,
        ]);

        app(SlotGenerator::class)->generate(today(), 14);

        activity()->enableLogging();
    }
}
