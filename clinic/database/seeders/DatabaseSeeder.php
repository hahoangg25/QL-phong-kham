<?php

namespace Database\Seeders;

use App\Services\SlotGenerator;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CatalogSeeder::class,
            StaffSeeder::class,
            MedicineSeeder::class,
            PatientSeeder::class,
        ]);

        // Sinh slot từ 30 ngày trước (cho dữ liệu lịch sử) đến 14 ngày tới (QT-09)
        $count = app(SlotGenerator::class)->generate(today()->subDays(30), 45);
        $this->command->info("Đã sinh {$count} khung giờ.");

        $this->call(HistorySeeder::class);
    }
}
