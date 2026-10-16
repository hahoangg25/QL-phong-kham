<?php

namespace Database\Seeders;

use App\Enums\ServiceType;
use App\Models\Catalog\Icd10Code;
use App\Models\Catalog\Room;
use App\Models\Catalog\Service;
use App\Models\Catalog\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /** slug => [tên, icon, ảnh, mô tả ngắn, từ khóa triệu chứng (BN-20)] */
    public const SPECIALTIES = [
        'nha-khoa-tong-quat' => [
            'Nha khoa Tổng quát & Điều trị', 'fa-tooth', 'nha-khoa-tong-quat.jpg',
            'Khám kiểm tra sức khỏe răng miệng tổng quát, lấy cao răng siêu âm không buốt, hàn trám răng thẩm mỹ, điều trị sâu răng và viêm nướu.',
            'đau răng, sâu răng, cạo vôi răng, lấy cao răng, trám răng, hàn răng, chảy máu chân răng, hôi miệng, ê buốt răng, sưng nướu, viêm lợi',
        ],
        'noi-nha-chua-tuy' => [
            'Nội nha & Chữa tủy', 'fa-syringe', 'noi-nha-chua-tuy.jpg',
            'Chẩn đoán và điều trị tủy răng bằng máy định vị chóp hiện đại, trám bít ống tủy nhiệt dẻo vô trùng, bảo tồn răng thật tối đa không đau.',
            'viêm tủy răng, chữa tủy, đau răng dữ dội, buốt lên tận đầu, đau răng về đêm, răng lung lay, áp xe chân răng, sưng mặt do răng, vỡ răng vào tủy',
        ],
        'chinh-nha-nieng-rang' => [
            'Chỉnh nha & Niềng răng', 'fa-teeth-open', 'chinh-nha-nieng-rang.jpg',
            'Khám, chụp phim và tư vấn niềng răng thẩm mỹ: mắc cài kim loại, mắc cài sứ, niềng răng máng trong suốt Invisalign, cân chỉnh khớp cắn chuẩn.',
            'niềng răng, chỉnh nha, răng hô, răng vẩu, răng móm, răng khấp khểnh, răng thưa, lệch khớp cắn, cắn hở, cắn sâu, máng trong suốt, invisalign',
        ],
        'tieu-phau-rang-khon' => [
            'Tiểu phẫu & Răng khôn', 'fa-user-doctor', 'tieu-phau-rang-khon.jpg',
            'Nhổ răng số 8 (răng khôn) mọc ngầm, mọc lệch không đau bằng công nghệ sóng siêu âm Piezotome, cắt lợi trùm, nhổ răng khó an toàn.',
            'nhổ răng khôn, răng số 8, răng khôn mọc lệch, răng mọc ngầm, đau răng số 8, sưng má răng khôn, cắt lợi trùm, nhổ răng piezotome, viêm quanh thân răng',
        ],
        'cay-ghep-implant' => [
            'Cấy ghép Implant', 'fa-screwdriver', 'cay-ghep-implant.jpg',
            'Trồng răng Implant kỹ thuật số không đau, cấy ghép trụ Implant Thụy Sĩ/Hàn Quốc, ghép xương hàm, giải pháp toàn hàm All-on-4 và All-on-6.',
            'trồng răng implant, cấy ghép implant, mất răng, rụng răng, mất răng lâu năm, gãy răng, cầu răng sứ, làm răng giả, cắm trụ implant, tiêu xương hàm',
        ],
        'rang-su-tham-my' => [
            'Răng sứ & Thẩm mỹ nụ cười', 'fa-gem', 'rang-su-tham-my.jpg',
            'Bọc răng sứ thẩm mỹ, dán sứ Veneer siêu mỏng bảo tồn răng gốc, tẩy trắng răng công nghệ Laser Whitening, thiết kế nụ cười toàn diện.',
            'bọc răng sứ, dán sứ veneer, răng ố vàng, tẩy trắng răng, răng xỉn màu, răng mẻ, làm răng thẩm mỹ, thiết kế nụ cười, răng nhiễm tetracycline',
        ],
    ];

    public function run(): void
    {
        $order = 1;
        foreach (self::SPECIALTIES as $slug => [$name, $icon, $image, $short, $keywords]) {
            $s = Specialty::create([
                'name' => $name,
                'slug' => $slug,
                'icon' => $icon,
                'image' => 'assets/img/specialties/'.$image,
                'short_description' => $short,
                'description' => $short."\n\nĐội ngũ bác sĩ Răng Hàm Mặt giàu kinh nghiệm, hệ thống ghế nha khoa và phòng phẫu thuật vô trùng chuẩn quốc tế. "
                    .'Bệnh nhân có thể đặt lịch hẹn trực tuyến để chủ động chọn bác sĩ và thời gian khám, tiết kiệm tối đa thời gian chờ đợi.',
                'keywords' => $keywords,
                'sort_order' => $order,
            ]);

            Room::create([
                'code' => 'P'.(100 + $order),
                'name' => 'Phòng '.$name,
                'floor' => $order <= 4 ? '1' : '2',
                'specialty_id' => $s->id,
            ]);
            $order++;
        }

        $this->seedServices();
        $this->seedIcd10();
    }

    protected function seedServices(): void
    {
        $sp = Specialty::pluck('id', 'slug');

        $services = [
            // Khám chuyên khoa nha
            ['KB01', 'Khám răng miệng tổng quát & Tư vấn chăm sóc nụ cười', ServiceType::Exam, 'nha-khoa-tong-quat', 100000],
            ['KB02', 'Khám, kiểm tra tủy & Đo độ nhạy cảm răng', ServiceType::Exam, 'noi-nha-chua-tuy', 150000],
            ['KB03', 'Khám, lập phác đồ Chỉnh nha & Tư vấn niềng răng', ServiceType::Exam, 'chinh-nha-nieng-rang', 200000],
            ['KB04', 'Khám tầm soát răng khôn & Đánh giá nguy cơ mọc lệch', ServiceType::Exam, 'tieu-phau-rang-khon', 150000],
            ['KB05', 'Khám tư vấn Cấy ghép Implant & Ghép xương hàm', ServiceType::Exam, 'cay-ghep-implant', 250000],
            ['KB06', 'Khám thiết kế nụ cười thẩm mỹ & Tư vấn răng sứ / Veneer', ServiceType::Exam, 'rang-su-tham-my', 200000],
            ['KB07', 'Khám chuyên gia Răng Hàm Mặt (PGS.TS / BSCKII)', ServiceType::Exam, null, 350000],

            // Cận lâm sàng & Chẩn đoán hình ảnh Nha khoa (Imaging / Lab)
            ['HA01', 'Chụp phim X-quang răng quanh chóp (Periapical)', ServiceType::Imaging, 'noi-nha-chua-tuy', 50000],
            ['HA02', 'Chụp phim X-quang toàn cảnh Panorama (Panorex)', ServiceType::Imaging, 'tieu-phau-rang-khon', 150000],
            ['HA03', 'Chụp cắt lớp vi tính CT Cone Beam 3D hàm mặt', ServiceType::Imaging, 'cay-ghep-implant', 350000],
            ['HA04', 'Scan dấu hàm kỹ thuật số 3D bằng máy Trios / iTero', ServiceType::Imaging, 'chinh-nha-nieng-rang', 200000],

            // Thủ thuật Nha khoa điều trị & thẩm mỹ (Procedure)
            ['TT01', 'Lấy cao răng siêu âm & Đánh bóng hai hàm', ServiceType::Procedure, 'nha-khoa-tong-quat', 200000],
            ['TT02', 'Trám răng thẩm mỹ Composite quang trùng hợp (1 răng)', ServiceType::Procedure, 'nha-khoa-tong-quat', 250000],
            ['TT03', 'Điều trị tủy răng trước (răng số 1 - 5) bằng máy', ServiceType::Procedure, 'noi-nha-chua-tuy', 600000],
            ['TT04', 'Điều trị tủy răng hàm lớn (răng số 6, 7)', ServiceType::Procedure, 'noi-nha-chua-tuy', 900000],
            ['TT05', 'Nhổ răng số 8 mọc lệch bằng sóng siêu âm Piezotome', ServiceType::Procedure, 'tieu-phau-rang-khon', 1500000],
            ['TT06', 'Nhổ răng lung lay / nhổ răng sữa trẻ em', ServiceType::Procedure, 'nha-khoa-tong-quat', 150000],
            ['TT07', 'Cắt lợi trùm điều trị viêm quanh thân răng khôn', ServiceType::Procedure, 'tieu-phau-rang-khon', 350000],
            ['TT08', 'Tẩy trắng răng công nghệ Laser Whitening tại phòng khám', ServiceType::Procedure, 'rang-su-tham-my', 1800000],
        ];

        foreach ($services as [$code, $name, $type, $slug, $price]) {
            Service::create([
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'specialty_id' => $slug ? $sp[$slug] : null,
                'price' => $price,
                'description' => $type === ServiceType::Exam ? 'Bác sĩ chuyên khoa Răng Hàm Mặt trực tiếp thăm khám, kiểm tra và tư vấn phác đồ điều trị.' : null,
            ]);
        }
    }

    protected function seedIcd10(): void
    {
        $rows = [];
        foreach (self::ICD10 as [$code, $name, $chapter]) {
            $rows[] = ['code' => $code, 'name' => $name, 'chapter' => $chapter, 'created_at' => now(), 'updated_at' => now()];
        }
        Icd10Code::insert($rows);
    }

    /** Danh mục bệnh lý chuyên khoa Răng Hàm Mặt */
    public const ICD10 = [
        ['MB01', 'Sâu ngà răng', 'Bệnh lý mô cứng của răng'],
        ['MB02', 'Sâu răng, không đặc hiệu', 'Bệnh lý mô cứng của răng'],
        ['MB03', 'Ê buốt ngà răng / Mòn cổ răng', 'Bệnh lý mô cứng của răng'],
        ['MB04', 'Viêm tủy răng (cấp và mạn tính)', 'Bệnh tủy và mô quanh cuống'],
        ['MB05', 'Viêm quanh cuống răng cấp', 'Bệnh tủy và mô quanh cuống'],
        ['MB06', 'Áp xe chân răng quanh chóp không có lỗ rò', 'Bệnh tủy và mô quanh cuống'],
        ['MB07', 'Viêm nướu (lợi) mạn tính', 'Bệnh nướu và mô nha chu'],
        ['MB08', 'Viêm nha chu mạn tính', 'Bệnh nướu và mô nha chu'],
        ['MB09', 'Răng mọc kẹt (Răng khôn số 8 mọc lệch)', 'Răng mọc kẹt và mọc ngầm'],
        ['MB10', 'Sai khớp cắn (Răng hô / móm)', 'Bất thường cung hàm & Khớp cắn'],
        ['MB11', 'Lệch lạc vị trí răng (Răng khấp khểnh, chen chúc)', 'Bất thường cung hàm & Khớp cắn'],
        ['MB12', 'Mất răng do nhổ bỏ hoặc chấn thương', 'Các rối loạn khác của răng và mô nâng đỡ'],
        ['MB13', 'Viêm loét miệng áp-tơ (Nhiệt miệng)', 'Bệnh của môi và niêm mạc miệng'],
        ['MB14', 'Viêm quanh thân răng cấp (Lợi trùm răng khôn)', 'Bệnh nướu và mô nha chu'],
    ];
}
