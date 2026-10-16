<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\ServiceOrderStatus;
use App\Enums\VisitStatus;
use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\AppointmentStatusLog;
use App\Models\Clinical\Diagnosis;
use App\Models\Catalog\Doctor;
use App\Models\Catalog\Icd10Code;
use App\Models\Billing\Invoice;
use App\Models\Clinical\LabResult;
use App\Models\Pharmacy\Medicine;
use App\Models\Pharmacy\MedicineBatch;
use App\Models\Clinical\Patient;
use App\Models\Billing\Payment;
use App\Models\Clinical\Prescription;
use App\Models\Clinical\Review;
use App\Models\Catalog\Service;
use App\Models\Clinical\ServiceOrder;
use App\Models\Pharmacy\StockTransaction;
use App\Models\Scheduling\TimeSlot;
use App\Models\User;
use App\Models\Clinical\Visit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Sinh dữ liệu mẫu Nha khoa: 30 ngày lịch sử khám, hàng đợi hôm nay, lịch hẹn sắp tới.
 */
class HistorySeeder extends Seeder
{
    /** slug => [mã bệnh lý, mã dịch vụ CLS, mã thuốc, triệu chứng] */
    protected const PROFILE = [
        'nha-khoa-tong-quat' => [
            ['MB01', 'MB02', 'MB07', 'MB03'],
            ['TT01', 'TT02'],
            ['T002', 'T013', 'T014'],
            ['Lấy cao răng định kỳ 6 tháng', 'Răng hàm dưới bị sâu lỗ đen thức ăn hay giắt vào', 'Ê buốt răng khi uống nước lạnh hoặc ăn đồ chua', 'Chảy máu chân răng mỗi khi đánh răng'],
        ],
        'noi-nha-chua-tuy' => [
            ['MB04', 'MB05', 'MB06'],
            ['HA01', 'TT03', 'TT04'],
            ['T001', 'T005', 'T006', 'T002'],
            ['Đau nhức răng dữ dội giật nhói lên tận đầu', 'Răng đau buốt không ngủ được về đêm', 'Chân răng nổi cục mủ nhỏ, khi cắn vào rất đau', 'Răng sâu vỡ lớn lộ tủy'],
        ],
        'chinh-nha-nieng-rang' => [
            ['MB10', 'MB11'],
            ['HA02', 'HA04', 'KB03'],
            ['T010', 'T014'],
            ['Tư vấn niềng răng khấp khểnh, chen chúc', 'Răng hàm trên bị hô, khó khép môi tự nhiên', 'Khám khớp cắn sâu và tư vấn niềng máng trong suốt Invisalign', 'Tái khám siết mắc cài niềng răng định kỳ'],
        ],
        'tieu-phau-rang-khon' => [
            ['MB09', 'MB14'],
            ['HA02', 'TT05', 'TT07'],
            ['T001', 'T004', 'T005', 'T008', 'T002'],
            ['Đau nhức góc hàm trong cùng bên dưới', 'Sưng má, khó há miệng do mọc răng khôn', 'Răng khôn mọc đâm xiên vào răng số 7', 'Lợi trùm răng khôn sưng tấy và đau rát'],
        ],
        'cay-ghep-implant' => [
            ['MB12', 'MB08'],
            ['HA03', 'KB05'],
            ['T004', 'T007', 'T008', 'T002'],
            ['Mất răng hàm số 6 đã lâu, ăn nhai khó khăn', 'Tư vấn cắm trụ Implant phục hồi răng đã mất', 'Gãy chân răng cửa do chấn thương, muốn cắm implant tức thì', 'Tái khám kiểm tra trụ Implant sau phẫu thuật'],
        ],
        'rang-su-tham-my' => [
            ['MB03', 'MB02', 'MB11'],
            ['TT08', 'KB06'],
            ['T013', 'T014'],
            ['Răng ố vàng nhiễm màu kháng sinh muốn bọc răng sứ', 'Răng cửa thưa và mẻ góc cắn muốn dán sứ Veneer', 'Tư vấn làm răng sứ thẩm mỹ Cercon HT', 'Răng bị mòn mặt nhai gây ê buốt'],
        ],
    ];

    protected Collection $patients;

    protected Collection $icd;

    protected Collection $services;

    protected Collection $medicines;

    protected array $staff = [];

    public function run(): void
    {
        activity()->disableLogging();

        $this->patients = Patient::with('user')->get();
        $this->icd = Icd10Code::pluck('name', 'code');
        $this->services = Service::all()->keyBy('code');
        $this->medicines = Medicine::all()->keyBy('code');

        $this->staff = [
            'receptionist' => User::where('email', 'letan@phongkham.test')->first(),
            'cashier' => User::where('email', 'thungan@phongkham.test')->first(),
            'pharmacist' => User::where('email', 'duocsi@phongkham.test')->first(),
            'lab' => User::where('email', 'ktv@phongkham.test')->first(),
        ];

        $doctors = Doctor::with('specialty', 'user')->get();

        $this->historicalVisits($doctors);
        $this->demoPatientHistory($doctors);
        $this->today($doctors);
        $this->upcoming($doctors);

        activity()->enableLogging();
    }

    /** 30 ngày qua: mỗi bác sĩ hoàn thành 2-4 ca/ngày */
    protected function historicalVisits(Collection $doctors): void
    {
        for ($d = 30; $d >= 1; $d--) {
            $date = today()->subDays($d);
            if ($date->isSunday()) {
                continue;
            }

            foreach ($doctors as $doctor) {
                $slots = TimeSlot::where('doctor_id', $doctor->id)->whereDate('date', $date)->get();
                if ($slots->isEmpty()) {
                    continue;
                }

                $chosenSlots = $slots->random(min($slots->count(), random_int(2, 4)))->sortBy('start_time');
                $queue = 1;

                foreach ($chosenSlots as $slot) {
                    $patient = $this->pickPatient($doctor);
                    $appt = $this->appointment($slot, $patient, AppointmentStatus::Completed);
                    $this->completedVisit($doctor, $patient, $slot->starts_at, $queue++, $appt);
                }
            }
        }
    }

    protected function pickPatient(Doctor $doctor): Patient
    {
        $slug = $doctor->specialty->slug;

        return $this->patients->filter(function (Patient $p) use ($slug) {
            $age = $p->age ?? 30;

            return match ($slug) {
                'cay-ghep-implant' => $age >= 30,
                'tieu-phau-rang-khon' => $age >= 18 && $age <= 45,
                'chinh-nha-nieng-rang' => $age >= 12 && $age <= 35,
                default => $age >= 10,
            };
        })->random();
    }

    protected function appointment(TimeSlot $slot, Patient $patient, AppointmentStatus $status, string $source = 'online', ?string $symptoms = null): Appointment
    {
        $doctor = $slot->doctor ?? Doctor::find($slot->doctor_id);
        $created = $slot->starts_at->copy()->subDays(random_int(1, 6))->setTime(random_int(8, 21), random_int(0, 59));
        $by = $patient->user ?? $this->staff['receptionist'];

        $appointment = Appointment::forceCreate([
            'code' => Appointment::generateUniqueCode(),
            'patient_id' => $patient->id,
            'booked_by' => $by->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $doctor->specialty_id,
            'time_slot_id' => $slot->id,
            'appointment_date' => $slot->date,
            'scheduled_at' => $slot->starts_at,
            'status' => $status,
            'source' => $patient->user ? $source : 'phone',
            'symptoms' => $symptoms ?? collect(self::PROFILE[$doctor->specialty->slug][3])->random(),
            'confirmed_at' => $status === AppointmentStatus::Pending ? null : $created->copy()->addHours(2),
            'confirmed_by' => $status === AppointmentStatus::Pending ? null : $this->staff['receptionist']->id,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? $slot->starts_at->copy()->subDay() : null,
            'cancel_reason' => $status === AppointmentStatus::Cancelled ? 'Bận việc đột xuất' : null,
            'cancelled_by' => $status === AppointmentStatus::Cancelled ? $by->id : null,
            'checked_in_at' => in_array($status, [AppointmentStatus::Completed, AppointmentStatus::CheckedIn, AppointmentStatus::InProgress, AppointmentStatus::AwaitingResults], true)
                ? $slot->starts_at->copy()->subMinutes(10) : null,
            'created_at' => $created,
            'updated_at' => $created,
        ]);

        $slot->increment('booked_count');
        $this->seedAppointmentLogs($appointment, $status, $created, $slot->starts_at);

        return $appointment;
    }

    protected function seedAppointmentLogs(Appointment $a, AppointmentStatus $final, Carbon $created, Carbon $slotAt): void
    {
        $chain = match ($final) {
            AppointmentStatus::Pending => [AppointmentStatus::Pending],
            AppointmentStatus::Confirmed => [AppointmentStatus::Pending, AppointmentStatus::Confirmed],
            AppointmentStatus::Cancelled => [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::Cancelled],
            AppointmentStatus::NoShow => [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::NoShow],
            AppointmentStatus::CheckedIn => [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn],
            AppointmentStatus::InProgress => [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, AppointmentStatus::InProgress],
            AppointmentStatus::AwaitingResults => [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, AppointmentStatus::InProgress, AppointmentStatus::AwaitingResults],
            default => [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, AppointmentStatus::InProgress, AppointmentStatus::Completed],
        };

        $prev = null;
        foreach ($chain as $idx => $status) {
            $at = match ($status) {
                AppointmentStatus::Pending => $created,
                AppointmentStatus::Confirmed => $created->copy()->addMinutes(15),
                AppointmentStatus::Cancelled, AppointmentStatus::NoShow => $slotAt->copy()->subHours(2),
                AppointmentStatus::CheckedIn => $slotAt->copy()->subMinutes(10),
                AppointmentStatus::InProgress => $slotAt,
                AppointmentStatus::AwaitingResults => $slotAt->copy()->addMinutes(15),
                AppointmentStatus::Completed => $slotAt->copy()->addMinutes(35),
            };

            AppointmentStatusLog::create([
                'appointment_id' => $a->id,
                'from_status' => $prev?->value,
                'to_status' => $status->value,
                'user_id' => match ($status) {
                    AppointmentStatus::Pending, AppointmentStatus::Cancelled => $a->booked_by,
                    AppointmentStatus::NoShow => null,
                    AppointmentStatus::InProgress, AppointmentStatus::AwaitingResults, AppointmentStatus::Completed => $a->doctor->user_id,
                    default => $this->staff['receptionist']->id,
                },
                'note' => match ($status) {
                    AppointmentStatus::Pending => 'Đặt lịch khám nha khoa',
                    AppointmentStatus::NoShow => 'Hệ thống: quá giờ chưa check-in',
                    AppointmentStatus::Cancelled => $a->cancel_reason,
                    default => null,
                },
                'created_at' => $at,
            ]);
            $prev = $status;
        }
    }

    protected function completedVisit(
        Doctor $doctor,
        Patient $patient,
        Carbon $at,
        int $queue,
        ?Appointment $appointment = null,
        bool $paid = true,
        bool $dispensed = true
    ): Visit {
        $slug = $doctor->specialty->slug;
        $profile = self::PROFILE[$slug] ?? self::PROFILE['nha-khoa-tong-quat'];
        [$icds, $serviceCodes, $medCodes, $symptoms] = $profile;

        $start = $at->copy()->addMinutes(random_int(2, 10));
        $end = $start->copy()->addMinutes(random_int(20, 35));

        $visit = Visit::forceCreate([
            'code' => Visit::generateUniqueCode(),
            'appointment_id' => $appointment?->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $doctor->specialty_id,
            'room_id' => $doctor->room_id,
            'visit_date' => $at->toDateString(),
            'queue_number' => $queue,
            'is_priority' => $patient->isPriority(),
            'queue_sort' => $at,
            'status' => VisitStatus::Completed,
            'type' => $appointment ? 'appointment' : 'walk_in',
            'checked_in_by' => $this->staff['receptionist']->id,
            'checked_in_at' => $at->copy()->subMinutes(10),
            'called_at' => $start,
            'started_at' => $start,
            'completed_at' => $start->copy()->addMinutes(random_int(10, 40)),
            'locked_at' => $start->copy()->addMinutes(40),
            'reason' => $appointment?->symptoms ?? collect($symptoms)->random(),
            'symptoms' => 'Bệnh nhân than phiền khó chịu vùng răng miệng khi ăn nhai hoặc khi uống đồ lạnh.',
            'pulse' => random_int(68, 86),
            'bp_systolic' => random_int(110, 135),
            'bp_diastolic' => random_int(70, 85),
            'temperature' => 36.6,
            'spo2' => 98,
            'weight' => random_int(45, 75),
            'height' => random_int(150, 175),
            'clinical_notes' => 'Niêm mạc miệng hồng hào, mô nha chu ổn định, khớp cắn tương đối đều. Đã kiểm tra kỹ các bề mặt răng trên ghế nha khoa.',
            'conclusion' => 'Chẩn đoán xác định bệnh lý răng miệng và hoàn tất điều trị nha khoa an toàn.',
            'advice' => 'Chải răng đúng cách ngày 2 lần bằng bàn chải lông mềm, dùng chỉ nha khoa sau ăn, súc miệng nước sát khuẩn Kin/Medoral. Tái khám định kỳ sau 6 tháng.',
            'created_at' => $at->copy()->subMinutes(10),
            'updated_at' => $start->copy()->addMinutes(40),
        ]);

        $codes = collect($icds)->random(min(count($icds), random_int(1, 2)))->values();
        foreach ($codes as $i => $code) {
            Diagnosis::forceCreate([
                'visit_id' => $visit->id,
                'icd10_code' => $code,
                'name' => $this->icd[$code] ?? $code,
                'is_primary' => $i === 0,
                'created_at' => $start,
                'updated_at' => $start,
            ]);
        }

        if ($serviceCodes && random_int(1, 100) <= 60) {
            foreach (collect($serviceCodes)->random(min(count($serviceCodes), random_int(1, 2))) as $code) {
                if (isset($this->services[$code])) {
                    $this->serviceOrder($visit, $this->services[$code], $doctor, $start, completed: true);
                }
            }
        }

        if (random_int(1, 100) <= 85) {
            $this->prescription($visit, $doctor, $medCodes, $start, $dispensed && $paid);
        }

        $this->invoice($visit, $start->copy()->addMinutes(45), $paid);

        if ($paid && $patient->user && random_int(1, 100) <= 40) {
            $rating = collect([5, 5, 5, 4, 4])->random();
            Review::forceCreate([
                'visit_id' => $visit->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $patient->id,
                'user_id' => $patient->user_id,
                'rating' => $rating,
                'comment' => collect([
                    'Bác sĩ thao tác rất nhẹ nhàng, lấy cao răng không hề bị ê buốt hay chảy máu.',
                    'Phòng khám nha khoa sạch sẽ, thơm tho, máy móc hiện đại và vô trùng rất cẩn thận.',
                    'Nhổ răng khôn bằng máy Piezotome nhanh và êm bất ngờ, về nhà không sưng đau nhiều.',
                    'Bác sĩ tư vấn niềng răng chi tiết, chụp phim và giải thích phác đồ rất dễ hiểu.',
                ])->random(),
                'created_at' => $end->copy()->addHours(random_int(2, 48)),
                'updated_at' => $end->copy()->addHours(random_int(2, 48)),
            ]);
        }

        return $visit;
    }

    protected function serviceOrder(Visit $visit, Service $service, Doctor $doctor, Carbon $at, bool $completed): ServiceOrder
    {
        $order = ServiceOrder::forceCreate([
            'visit_id' => $visit->id,
            'service_id' => $service->id,
            'ordered_by' => $doctor->user_id,
            'price' => $service->price,
            'status' => $completed ? ServiceOrderStatus::Completed : ServiceOrderStatus::Pending,
            'started_at' => $completed ? $at->copy()->addMinutes(5) : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        if ($completed) {
            [$result, $conclusion] = $this->labText($service->code);
            LabResult::forceCreate([
                'service_order_id' => $order->id,
                'performed_by' => $this->staff['lab']->id,
                'result' => $result,
                'conclusion' => $conclusion,
                'completed_at' => $at->copy()->addMinutes(25),
                'created_at' => $at->copy()->addMinutes(25),
                'updated_at' => $at->copy()->addMinutes(25),
            ]);
        }

        return $order;
    }

    protected function labText(string $code): array
    {
        return match ($code) {
            'HA01' => [
                "Hình ảnh X-quang răng quanh chóp: Vùng cuống răng dây chằng nha chu giãn nhẹ. Ống tủy hẹp, không có tiêu xương bệnh lý.",
                'Chụp quanh chóp: Hỗ trợ kiểm tra ống tủy và chân răng trước điều trị.',
            ],
            'HA02' => [
                "Phim toàn cảnh Panorama (Panorex): Cung răng hàm trên và hàm dưới phát triển đều. Răng 38 mọc lệch nghiêng 75 độ húc nhẹ thân răng 37. Răng 48 mọc kẹt.",
                'Răng khôn hàm dưới mọc lệch, chỉ định nhổ răng bằng sóng siêu âm Piezotome phòng ngừa biến chứng.',
            ],
            'HA03' => [
                "Phim CT Cone Beam 3D: Vị trí mất răng 36 chiều cao xương ổ răng 12.5mm, bề rộng sống hàm 7.2mm. Cách ống thần kinh răng dưới 4.0mm.",
                'Đủ điều kiện thể tích xương hàm cấy ghép trụ Implant kích thước 4.0 x 10mm.',
            ],
            'HA04' => [
                "Scan dấu hàm 3D Trios: Sai khớp cắn hạng II nhẹ, độ cắn chìa 3.5mm, cung răng hàm dưới chen khúc nhẹ vùng răng cửa.",
                'Dữ liệu số hóa 3D sẵn sàng cho kế hoạch niềng răng máng trong suốt.',
            ],
            'TT01' => [
                "Lấy cao răng siêu âm toàn bộ hai hàm trên và dưới, làm sạch túi lợi và đánh bóng men răng.",
                'Cao răng được loại bỏ hoàn toàn, nướu răng sạch sẽ, không chảy máu.',
            ],
            'TT02' => [
                "Làm sạch xoang sâu răng, cách ly bông gòn, xoi mòn acid, bôi keo dán và trám thẩm mỹ từng lớp Composite quang trùng hợp.",
                'Phục hồi hình thể và khớp cắn răng sâu thẩm mỹ như răng thật.',
            ],
            default => [
                "Thủ thuật nha khoa thực hiện nhẹ nhàng, bệnh nhân hợp tác tốt, vết can thiệp êm.",
                'Đã hoàn thành thủ thuật nha khoa an toàn.',
            ],
        };
    }

    protected function prescription(Visit $visit, Doctor $doctor, array $medCodes, Carbon $at, bool $dispensed): Prescription
    {
        $p = Prescription::forceCreate([
            'code' => Prescription::generateUniqueCode(),
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'doctor_id' => $doctor->id,
            'status' => $dispensed ? PrescriptionStatus::Dispensed : PrescriptionStatus::Pending,
            'note' => 'Uống thuốc đúng giờ sau ăn. Kiêng đồ ăn quá nóng/cay sau thủ thuật. Tái khám sau 5 - 7 ngày.',
            'dispensed_by' => $dispensed ? $this->staff['pharmacist']->id : null,
            'dispensed_at' => $dispensed ? $at->copy()->addHour() : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        foreach (collect($medCodes)->random(min(count($medCodes), random_int(2, 3))) as $code) {
            $m = $this->medicines[$code] ?? null;
            if (! $m) {
                continue;
            }

            $days = collect([3, 5, 7, 10])->random();
            $perDay = $m->unit === 'Viên' ? 2 : 1;
            $qty = in_array($m->unit, ['Tuýp', 'Lọ', 'Bình', 'Chai'], true) ? 1 : $perDay * $days;

            $p->items()->create([
                'medicine_id' => $m->id,
                'quantity' => $qty,
                'dosage' => match ($m->route) {
                    'Súc miệng' => 'Súc 15ml/lần, ngày 2 - 3 lần',
                    'Bôi ngoài da' => 'Chấm bôi 1 lượng nhỏ vào vùng tổn thương',
                    'Ngậm dưới lưỡi' => 'Ngậm 2 viên/lần, ngày 2 lần',
                    default => 'Uống 1 viên/lần, ngày 2 lần sau ăn',
                },
                'usage' => match ($m->route) {
                    'Súc miệng' => 'Súc miệng sau chải răng 30 giây rồi nhổ bỏ, không ăn uống trong 30 phút sau khi súc',
                    'Bôi ngoài da' => 'Thoa nhẹ lên vùng nhiệt miệng hoặc nướu sưng sau khi làm sạch răng',
                    'Ngậm dưới lưỡi' => 'Để thuốc tan từ từ dưới lưỡi, không nhai nuốt',
                    default => 'Uống sau bữa ăn với nhiều nước lọc',
                },
                'days' => $days,
                'unit_price' => $m->price,
                'amount' => $qty * $m->price,
            ]);

            if ($dispensed) {
                $this->deductFefo($m, $qty, $p, $at->copy()->addHour());
            }
        }

        return $p;
    }

    protected function deductFefo(Medicine $m, int $qty, Prescription $p, Carbon $at): void
    {
        $batches = MedicineBatch::where('medicine_id', $m->id)->where('remaining', '>', 0)
            ->whereDate('expiry_date', '>=', today())->orderBy('expiry_date')->get();

        foreach ($batches as $batch) {
            if ($qty <= 0) {
                break;
            }
            $take = min($qty, $batch->remaining);
            $batch->decrement('remaining', $take);
            StockTransaction::forceCreate([
                'medicine_id' => $m->id,
                'medicine_batch_id' => $batch->id,
                'type' => 'dispense',
                'quantity' => -$take,
                'reference_type' => Prescription::class,
                'reference_id' => $p->id,
                'note' => 'Cấp phát đơn '.$p->code,
                'user_id' => $this->staff['pharmacist']->id,
                'created_at' => $at,
            ]);
            $qty -= $take;
        }
    }

    protected function invoice(Visit $visit, Carbon $at, bool $paid): Invoice
    {
        $visit->load(['doctor', 'serviceOrders.service', 'prescription.items.medicine', 'appointment']);

        $invoice = Invoice::forceCreate([
            'code' => Invoice::generateUniqueCode(),
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'status' => InvoiceStatus::Unpaid,
            'created_by' => $visit->doctor->user_id,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $invoice->items()->create([
            'item_type' => 'exam',
            'description' => 'Phí khám - '.$visit->doctor->display_name,
            'quantity' => 1,
            'unit_price' => $visit->doctor->consultation_fee,
            'amount' => $visit->doctor->consultation_fee,
        ]);

        foreach ($visit->serviceOrders as $o) {
            $invoice->items()->create([
                'item_type' => 'service',
                'description' => $o->service->name,
                'quantity' => 1,
                'unit_price' => $o->price,
                'amount' => $o->price,
            ]);
        }

        foreach ($visit->prescription?->items ?? [] as $i) {
            $invoice->items()->create([
                'item_type' => 'medicine',
                'description' => $i->medicine->fullName().' ('.$i->medicine->unit.')',
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
                'amount' => $i->amount,
            ]);
        }

        $subtotal = (int) $invoice->items()->sum('amount');
        $invoice->forceFill(['subtotal' => $subtotal, 'total' => $subtotal])->save();

        if ($paid) {
            $method = collect([PaymentMethod::Cash, PaymentMethod::Cash, PaymentMethod::Transfer, PaymentMethod::Vnpay])->random();
            Payment::forceCreate([
                'code' => 'TT'.$at->format('ymdHis').strtoupper(\Illuminate\Support\Str::random(4)),
                'invoice_id' => $invoice->id,
                'patient_id' => $visit->patient_id,
                'type' => 'payment',
                'method' => $method,
                'amount' => $subtotal,
                'status' => PaymentStatus::Success,
                'received_by' => $this->staff['cashier']->id,
                'paid_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $invoice->forceFill([
                'status' => InvoiceStatus::Paid,
                'paid_amount' => $subtotal,
                'payment_method' => $method,
                'cashier_id' => $this->staff['cashier']->id,
                'paid_at' => $at,
            ])->save();
        }

        return $invoice;
    }

    /** Lịch sử riêng cho tài khoản demo benhnhan@phongkham.test */
    protected function demoPatientHistory(Collection $doctors): void
    {
        $self = Patient::where('full_name', 'Nguyễn Văn An')->first();
        $mother = Patient::where('full_name', 'Lê Thị Bình')->first();
        $child = Patient::where('full_name', 'Nguyễn Minh Khôi')->first();
        $bySlug = $doctors->keyBy(fn ($d) => $d->specialty->slug);

        foreach ([[$self, 'nha-khoa-tong-quat', 20], [$mother, 'cay-ghep-implant', 12], [$child, 'chinh-nha-nieng-rang', 6]] as [$patient, $slug, $daysAgo]) {
            $doctor = $bySlug[$slug] ?? $doctors->first();
            $slot = TimeSlot::where('doctor_id', $doctor->id)->whereDate('date', '<=', today()->subDays($daysAgo))
                ->orderByDesc('date')->orderBy('start_time')->first();
            if (! $slot) {
                continue;
            }
            $appt = $this->appointment($slot, $patient, AppointmentStatus::Completed);
            $this->completedVisit($doctor, $patient, $slot->starts_at, 20, $appt);
        }

        // Một lần vắng mặt để minh họa đếm no-show
        $targetDoc = $bySlug['tieu-phau-rang-khon'] ?? $doctors->first();
        $slot = TimeSlot::where('doctor_id', $targetDoc->id)->whereDate('date', '<=', today()->subDays(9))->orderByDesc('date')->first();
        if ($slot) {
            $this->appointment($slot, $self, AppointmentStatus::NoShow);
            $self->user->update(['no_show_count' => 1]);
        }
    }

    /** Hôm nay: hàng đợi đang chờ, lịch đã xác nhận, các ca minh họa */
    protected function today(Collection $doctors): void
    {
        $date = today();
        $taken = [];

        foreach ($doctors as $idx => $doctor) {
            $slots = TimeSlot::where('doctor_id', $doctor->id)->whereDate('date', $date)->orderBy('start_time')->get();
            if ($slots->isEmpty()) {
                continue;
            }

            // 3 người đã check-in đang chờ khám (hàng đợi demo)
            foreach ($slots->take(3) as $i => $slot) {
                $patient = $this->uniquePatient($doctor, $taken);
                $status = $idx === 0 && $i === 0 ? AppointmentStatus::InProgress : AppointmentStatus::CheckedIn;
                $appt = $this->appointment($slot, $patient, $status);
                Visit::forceCreate([
                    'code' => Visit::generateUniqueCode(),
                    'appointment_id' => $appt->id,
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'specialty_id' => $doctor->specialty_id,
                    'room_id' => $doctor->room_id,
                    'visit_date' => $date,
                    'queue_number' => $i + 1,
                    'is_priority' => $patient->isPriority(),
                    'queue_sort' => now()->subMinutes(60 - $i * 15),
                    'status' => $status === AppointmentStatus::InProgress ? VisitStatus::InProgress : VisitStatus::Waiting,
                    'type' => 'appointment',
                    'checked_in_by' => $this->staff['receptionist']->id,
                    'checked_in_at' => now()->subMinutes(60 - $i * 15),
                    'called_at' => $status === AppointmentStatus::InProgress ? now()->subMinutes(10) : null,
                    'started_at' => $status === AppointmentStatus::InProgress ? now()->subMinutes(10) : null,
                    'reason' => $appt->symptoms,
                ]);
            }
        }

        // Demo các ca minh họa thu ngân và dược sĩ
        $paidOptions = [[false, false], [true, false]];
        foreach ($paidOptions as $i => [$paid, $dispensed]) {
            $doctor = $doctors[$i] ?? $doctors->first();
            $patient = $this->uniquePatient($doctor, $taken);
            $this->completedVisit($doctor, $patient, now()->subMinutes(120 - $i * 30), 50 + $i, null, $paid, $dispensed);
        }

        // CLS: 1 ca đang chờ kết quả chụp phim toàn cảnh Panorama
        $doctor = $doctors->first();
        $patient = $this->uniquePatient($doctor, $taken);
        $visit = Visit::forceCreate([
            'code' => Visit::generateUniqueCode(),
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $doctor->specialty_id,
            'room_id' => $doctor->room_id,
            'visit_date' => $date,
            'queue_number' => 60,
            'is_priority' => $patient->isPriority(),
            'queue_sort' => now()->subHour(),
            'status' => VisitStatus::AwaitingResults,
            'type' => 'walk_in',
            'checked_in_by' => $this->staff['receptionist']->id,
            'checked_in_at' => now()->subHour(),
            'called_at' => now()->subMinutes(50),
            'started_at' => now()->subMinutes(50),
            'reason' => 'Đau nhức góc hàm trong cùng bên dưới',
            'symptoms' => 'Răng khôn mọc lệch sưng đau, cắn chạm nướu gây khó há miệng',
            'pulse' => 82, 'bp_systolic' => 125, 'bp_diastolic' => 80, 'temperature' => 36.8, 'spo2' => 98, 'weight' => 65, 'height' => 168,
        ]);
        if (isset($this->services['HA02'])) {
            $this->serviceOrder($visit, $this->services['HA02'], $doctor, now()->subMinutes(40), completed: false);
        }
    }

    protected function uniquePatient(Doctor $doctor, array &$taken): Patient
    {
        for ($try = 0; $try < 20; $try++) {
            $p = $this->pickPatient($doctor);
            $key = $p->id.'-'.$doctor->specialty_id;
            if (! isset($taken[$key])) {
                $taken[$key] = true;

                return $p;
            }
        }

        return $this->pickPatient($doctor);
    }

    /** 14 ngày tới: lịch đã đặt, có lịch chờ xác nhận */
    protected function upcoming(Collection $doctors): void
    {
        $demo = Patient::where('full_name', 'Nguyễn Văn An')->first();
        $mother = Patient::where('full_name', 'Lê Thị Bình')->first();
        $taken = [];

        for ($d = 1; $d <= 14; $d++) {
            foreach ($doctors as $doctor) {
                $slots = TimeSlot::where('doctor_id', $doctor->id)->whereDate('date', today()->addDays($d))->get();
                foreach ($slots as $slot) {
                    if (random_int(1, 100) > 22) {
                        continue;
                    }
                    $patient = $this->uniquePatient($doctor, $taken);
                    $key = $patient->id.'-'.$slot->date->toDateString();
                    if (isset($taken[$key])) {
                        continue;
                    }
                    $taken[$key] = true;
                    $this->appointment($slot, $patient, random_int(1, 100) <= 30 ? AppointmentStatus::Pending : AppointmentStatus::Confirmed);
                }
            }
        }

        // Lịch sắp tới của tài khoản demo
        $bySlug = $doctors->keyBy(fn ($d) => $d->specialty->slug);
        $docTongQuat = $bySlug['nha-khoa-tong-quat'] ?? $doctors->first();
        $slot = TimeSlot::where('doctor_id', $docTongQuat->id)->whereDate('date', '>', today()->addDay())
            ->whereColumn('booked_count', '<', 'capacity')->orderBy('date')->orderBy('start_time')->skip(2)->first();
        if ($slot) {
            $this->appointment($slot, $demo, AppointmentStatus::Confirmed, symptoms: 'Lấy cao răng siêu âm và trám răng sâu kẽ định kỳ');
        }

        $docImplant = $bySlug['cay-ghep-implant'] ?? $doctors->last();
        $slot = TimeSlot::where('doctor_id', $docImplant->id)->whereDate('date', '>', today()->addDays(3))
            ->whereColumn('booked_count', '<', 'capacity')->orderBy('date')->orderBy('start_time')->first();
        if ($slot) {
            $this->appointment($slot, $mother, AppointmentStatus::Pending, symptoms: 'Khám tư vấn cấy ghép trụ Implant phục hồi răng hàm');
        }
    }
}
