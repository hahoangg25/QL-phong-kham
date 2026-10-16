<?php

namespace Database\Seeders;

use App\Models\Pharmacy\Medicine;
use App\Models\Pharmacy\MedicineBatch;
use App\Models\Pharmacy\StockReceipt;
use App\Models\Pharmacy\StockTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class MedicineSeeder extends Seeder
{
    /** [mã, tên, hoạt chất, hàm lượng, dạng, đơn vị, đường dùng, giá bán, định mức, hãng] */
    public const MEDICINES = [
        ['T001', 'Rodogyl', 'Spiramycin + Metronidazol', '750.000IU/125mg', 'Viên nén bao phim', 'Viên', 'Uống', 6500, 100, 'Sanofi (Pháp)'],
        ['T002', 'Nước súc miệng Kin Gingival', 'Chlorhexidine Digluconate', '0.12% - 250ml', 'Dung dịch súc miệng sát khuẩn', 'Chai', 'Súc miệng', 135000, 60, 'Laboratorios Kin (Tây Ban Nha)'],
        ['T003', 'Nước súc miệng Medoral', 'Chlorhexidine digluconate', '0.2% - 250ml', 'Dung dịch súc miệng điều trị viêm nướu', 'Chai', 'Súc miệng', 95000, 60, 'Dược Merap'],
        ['T004', 'Augmentin 625mg', 'Amoxicillin + Acid clavulanic', '625mg', 'Viên nén bao phim', 'Viên', 'Uống', 14000, 80, 'GSK (Anh)'],
        ['T005', 'Ibuprofen Stada 400', 'Ibuprofen', '400mg', 'Viên nén bao phim', 'Viên', 'Uống', 2000, 150, 'Stada'],
        ['T006', 'Panadol Extra', 'Paracetamol + Cafein', '500mg/65mg', 'Viên nén bao phim', 'Viên', 'Uống', 2000, 200, 'GSK'],
        ['T007', 'Efferalgan Codeine', 'Paracetamol + Codein phosphat', '500mg/30mg', 'Viên sủi giảm đau mạnh', 'Viên', 'Uống', 8500, 50, 'UPSA (Pháp)'],
        ['T008', 'Alpha Chymotrypsin', 'Chymotrypsine', '4.2mg (4200 UI)', 'Viên ngậm dưới lưỡi chống phù nề', 'Viên', 'Ngậm dưới lưỡi', 3500, 150, 'Sanofi'],
        ['T009', 'Medrol 16mg', 'Methylprednisolon', '16mg', 'Viên nén kháng viêm', 'Viên', 'Uống', 4500, 60, 'Pfizer (Mỹ)'],
        ['T010', 'Gel Kamistad-Gel N', 'Lidocain HCl + Dịch chiết hoa cúc', '10g', 'Gel bôi giảm đau viêm nướu, nhiệt miệng', 'Tuýp', 'Bôi ngoài da', 58000, 50, 'Stada (Đức)'],
        ['T011', 'Gel Oracortia', 'Triamcinolone acetonide', '0.1% - 5g', 'Thuốc mỡ bôi niêm mạc miệng nhiệt miệng', 'Tuýp', 'Bôi ngoài da', 28000, 60, 'Dược phẩm Thái Lan'],
        ['T012', 'Gel PerioKin', 'Chlorhexidine', '0.2% - 30ml', 'Gel bôi điều trị nha chu và vết nhổ răng', 'Tuýp', 'Bôi ngoài da', 120000, 40, 'Laboratorios Kin'],
        ['T013', 'Kem đánh răng Sensodyne Rapid Relief', 'Strontium acetate + Sodium fluoride', '100g', 'Kem đánh răng giảm ê buốt ngà', 'Tuýp', 'Dùng ngoài', 78000, 50, 'GSK'],
        ['T014', 'Nước muối sinh lý Natri Clorid 0.9%', 'Natri clorid', '0.9% - 500ml', 'Dung dịch súc rửa khoang miệng', 'Chai', 'Súc miệng', 8000, 200, 'Bidiphar'],
        ['T015', 'Vitamin C 500', 'Acid ascorbic', '500mg', 'Viên nén tăng sức bền thành mạch và nướu', 'Viên', 'Uống', 1000, 100, 'DHG Pharma'],
    ];

    public function run(): void
    {
        $pharmacist = User::role('pharmacist')->first() ?? User::first();

        $receipt = StockReceipt::create([
            'code' => 'PNK'.today()->format('ymd').'0001',
            'supplier' => 'Công ty Cổ phần Thiết bị & Dược phẩm Nha khoa Sài Gòn',
            'received_date' => today()->subDays(45),
            'note' => 'Nhập tồn kho ban đầu - Kho Dược & Vật tư Nha khoa',
            'created_by' => $pharmacist->id,
        ]);

        $total = 0;
        foreach (self::MEDICINES as $i => [$code, $name, $ingredient, $strength, $form, $unit, $route, $price, $min, $maker]) {
            $medicine = Medicine::create([
                'code' => $code,
                'name' => $name,
                'active_ingredient' => $ingredient,
                'strength' => $strength,
                'dosage_form' => $form,
                'unit' => $unit,
                'route' => $route,
                'price' => $price,
                'min_stock' => $min,
                'manufacturer' => $maker,
            ]);

            // Hai lô: lô hết hạn sớm và lô hết hạn muộn để minh họa FEFO
            $base = $min * 8;
            $batches = [
                ['L'.($i + 1).'A', today()->addMonths(5 + $i % 4), (int) ($base * 0.6)],
                ['L'.($i + 1).'B', today()->addMonths(18 + $i % 6), $base],
            ];

            // Dữ liệu minh họa cảnh báo kho (TD-10)
            if ($code === 'T007') {
                $batches = [['L07A', today()->addMonths(10), 8]]; // tồn dưới định mức
            }
            if ($code === 'T002') {
                $batches[0][1] = today()->addDays(25); // lô sắp hết hạn
            }
            if ($code === 'T001') {
                $batches[] = ['L01X', today()->subDays(10), 6]; // lô đã hết hạn còn tồn
            }

            foreach ($batches as [$batchNo, $expiry, $qty]) {
                $importPrice = (int) round($price * 0.7, -2);
                $batch = MedicineBatch::create([
                    'medicine_id' => $medicine->id,
                    'stock_receipt_id' => $receipt->id,
                    'batch_no' => $batchNo,
                    'expiry_date' => $expiry,
                    'quantity' => $qty,
                    'remaining' => $qty,
                    'import_price' => $importPrice,
                ]);
                StockTransaction::forceCreate([
                    'medicine_id' => $medicine->id,
                    'medicine_batch_id' => $batch->id,
                    'type' => 'import',
                    'quantity' => $qty,
                    'reference_type' => StockReceipt::class,
                    'reference_id' => $receipt->id,
                    'note' => 'Nhập theo phiếu '.$receipt->code,
                    'user_id' => $pharmacist->id,
                    'created_at' => now()->subDays(45),
                ]);
                $total += $qty * $importPrice;
            }
        }

        $receipt->update(['total_amount' => $total]);
    }
}
