<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Enums\ServiceOrderStatus;
use App\Enums\VisitStatus;
use App\Models\Scheduling\LeaveRequest;
use App\Models\Pharmacy\Medicine;
use App\Models\Clinical\Patient;
use App\Models\Catalog\Service;
use App\Models\User;
use App\Models\Clinical\Visit;
use App\Services\QueueService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phân hệ bác sĩ (BS-01..BS-13) và cận lâm sàng (CL-01..CL-04). */
class ClinicalTest extends TestCase
{
    protected function walkIn(string $doctorEmail = 'bs.quang@phongkham.test'): Visit
    {
        $doctor = User::where('email', $doctorEmail)->firstOrFail()->doctor;
        $patient = Patient::whereNull('user_id')->firstOrFail();
        $receptionist = User::where('email', 'letan@phongkham.test')->firstOrFail();

        return app(QueueService::class)->walkIn($patient, $doctor, $receptionist, 'Đau đầu, chóng mặt');
    }

    public function test_full_examination_flow_with_lab_result_and_prescription(): void
    {
        Storage::fake('local');
        $visit = $this->walkIn();
        $doctorUser = $this->loginAs('bs.quang@phongkham.test');

        // BS-03: hàng đợi và gọi bệnh nhân
        $this->get(route('staff.exam.index'))->assertOk()->assertSee($visit->patient->full_name);
        $this->get(route('staff.exam.index', ['partial' => 1]))->assertOk()->assertSee('Danh sách chờ khám');
        $this->post(route('staff.exam.call-next'))->assertRedirect(route('staff.exam.show', $visit));
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
        $this->get(route('staff.exam.show', $visit))->assertOk()->assertSee($visit->patient->code);
        $this->post(route('staff.exam.recall', $visit))->assertRedirect();

        // BS-05: khám lâm sàng + kiểm tra giới hạn sinh hiệu
        $this->put(route('staff.exam.clinical', $visit), [
            'reason' => 'Đau đầu', 'pulse' => 80, 'bp_systolic' => 120, 'bp_diastolic' => 80,
            'temperature' => 37.2, 'weight' => 60, 'height' => 165, 'clinical_notes' => 'Tỉnh, tiếp xúc tốt',
        ])->assertRedirect(route('staff.exam.show', [$visit, 'tab' => 'clinical']));
        $this->assertSame(80, (int) $visit->fresh()->pulse);
        $this->put(route('staff.exam.clinical', $visit), ['pulse' => 999])->assertSessionHasErrors('pulse');

        // BS-06: chẩn đoán - bệnh chính ở dòng thứ hai
        $this->put(route('staff.exam.diagnoses', $visit), [
            'diagnoses' => [
                ['icd10_code' => 'R51', 'name' => 'Đau đầu'],
                ['icd10_code' => 'G43.9', 'name' => 'Migraine (đau nửa đầu), không đặc hiệu', 'note' => 'Theo dõi'],
            ],
            'primary' => 1,
        ])->assertRedirect();
        $this->assertSame('G43.9', $visit->diagnoses()->where('is_primary', true)->value('icd10_code'));
        $this->assertSame(2, $visit->diagnoses()->count());
        $this->put(route('staff.exam.diagnoses', $visit), ['diagnoses' => [['icd10_code' => 'XXX', 'name' => 'Sai']]])
            ->assertSessionHasErrors('diagnoses.0.icd10_code');

        // BS-07: chỉ định CLS -> chờ kết quả, chưa được hoàn thành
        $service = Service::where('code', 'XN01')->firstOrFail();
        $this->post(route('staff.exam.orders', $visit), ['service_ids' => [$service->id], 'note' => 'Nhịn ăn'])->assertRedirect();
        $this->assertSame(VisitStatus::AwaitingResults, $visit->fresh()->status);
        $order = $visit->serviceOrders()->firstOrFail();
        $this->get(route('staff.exam.show', [$visit, 'tab' => 'orders']))->assertOk()->assertSee('Đang chờ kết quả cận lâm sàng');
        $this->post(route('staff.exam.complete', $visit))->assertSessionHas('error');

        // CL-01..CL-04: KTV thực hiện và trả kết quả có tệp đính kèm
        $this->loginAs('ktv@phongkham.test');
        $this->get(route('staff.lab.index'))->assertOk()->assertSee($visit->patient->full_name);
        $this->get(route('staff.lab.index', ['tab' => 'in_progress']))->assertOk();
        $this->get(route('staff.lab.index', ['tab' => 'completed', 'type' => 'lab', 'q' => 'abc']))->assertOk();
        $this->get(route('staff.lab.show', $order))->assertOk()->assertSee($service->name);
        $this->post(route('staff.lab.start', $order))->assertRedirect(route('staff.lab.show', $order));
        $this->assertSame(ServiceOrderStatus::InProgress, $order->fresh()->status);
        $this->post(route('staff.lab.result', $order), [
            'result' => "WBC: 7.0 G/L\nHGB: 140 g/L",
            'conclusion' => 'Bình thường',
            'attachment' => UploadedFile::fake()->create('kq.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $order->refresh();
        $this->assertSame(ServiceOrderStatus::Completed, $order->status);
        $this->assertNotNull($order->result);
        Storage::disk('local')->assertExists($order->result->attachment_path);
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
        $this->assertGreaterThan(0, $doctorUser->notifications()->count());
        $this->get(route('staff.lab.show', $order))->assertOk()->assertSee('Đã trả kết quả');

        // BS-08, BS-09: bác sĩ xem kết quả, kê đơn
        $this->loginAs('bs.quang@phongkham.test');
        $this->get(route('staff.exam.show', [$visit, 'tab' => 'orders']))->assertOk()->assertSee('Bình thường');
        $this->get(route('staff.lab-results.attachment', $order->result))->assertOk();
        $medicine = Medicine::where('code', 'T001')->firstOrFail();
        $this->put(route('staff.exam.prescription', $visit), [
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 10, 'dosage' => '1 viên/lần', 'usage' => 'Uống khi đau', 'days' => 5],
                ['medicine_id' => '', 'quantity' => '', 'dosage' => ''],
            ],
            'note' => 'Nghỉ ngơi',
        ])->assertRedirect(route('staff.exam.show', [$visit, 'tab' => 'prescription']));
        $this->assertSame(1, $visit->prescription()->firstOrFail()->items()->count());
        $this->put(route('staff.exam.prescription', $visit), [
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 99999, 'dosage' => '1 viên']],
        ])->assertSessionHasErrors('items.0.quantity');

        // BS-13: in ấn, xem bệnh án chỉ đọc
        $this->get(route('staff.records.print-prescription', $visit))->assertOk()->assertSee('ĐƠN THUỐC');
        $this->get(route('staff.records.print-orders', $visit))->assertOk()->assertSee('PHIẾU CHỈ ĐỊNH');
        $this->get(route('staff.records.show', $visit))->assertOk();

        // BS-11, BS-12: kết luận, hẹn tái khám, hoàn thành
        $this->put(route('staff.exam.clinical', $visit), [
            'tab' => 'conclusion', 'conclusion' => 'Migraine', 'advice' => 'Ngủ đủ giấc',
            'follow_up_date' => today()->addDays(7)->toDateString(),
        ])->assertRedirect(route('staff.exam.show', [$visit, 'tab' => 'conclusion']));
        $this->post(route('staff.exam.complete', $visit))->assertRedirect(route('staff.exam.index'));
        $visit->refresh();
        $this->assertSame(VisitStatus::Completed, $visit->status);
        $this->assertTrue($visit->isLocked());
        $this->assertNotNull($visit->invoice);
        $this->assertSame(3, $visit->invoice->items()->count()); // phí khám + CLS + thuốc

        // BR-08: bệnh án đã khóa chỉ ghi bổ sung
        $this->put(route('staff.exam.clinical', $visit), ['pulse' => 70])->assertSessionHas('error');
        $this->post(route('staff.exam.addendum', $visit), ['content' => 'Bổ sung: bệnh nhân gọi lại báo đỡ đau'])->assertRedirect();
        $this->assertSame(1, $visit->addenda()->count());
        $this->get(route('staff.exam.show', $visit))->assertOk()->assertSee('Bổ sung: bệnh nhân gọi lại báo đỡ đau');

        // KTV không được sửa kết quả của bệnh án đã khóa
        $this->loginAs('ktv@phongkham.test');
        $this->post(route('staff.lab.result', $order), ['result' => 'Sửa'])->assertSessionHas('error');

        // HT-09: mọi lần xem hồ sơ y tế đều được ghi nhật ký
        $this->assertDatabaseHas('activity_log', ['event' => 'viewed', 'subject_type' => Visit::class, 'subject_id' => $visit->id]);
    }

    public function test_cancel_pending_order_returns_visit_to_in_progress(): void
    {
        $visit = $this->walkIn();
        $this->loginAs('bs.quang@phongkham.test');
        $this->post(route('staff.exam.call', $visit))->assertRedirect(route('staff.exam.show', $visit));

        $service = Service::where('code', 'HA01')->firstOrFail();
        $this->post(route('staff.exam.orders', $visit), ['service_ids' => [$service->id]]);
        $order = $visit->serviceOrders()->firstOrFail();

        $this->delete(route('staff.exam.orders.cancel', $order))->assertRedirect();
        $this->assertSame(ServiceOrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
    }

    public function test_skip_moves_called_patient_back_to_queue(): void
    {
        $visit = $this->walkIn();
        $this->loginAs('bs.quang@phongkham.test');
        $this->post(route('staff.exam.call', $visit));
        $this->post(route('staff.exam.skip', $visit))->assertRedirect(route('staff.exam.index'));
        $this->assertSame(VisitStatus::Waiting, $visit->fresh()->status);
        $this->get(route('staff.exam.show', $visit))->assertOk()->assertSee('Gọi vào khám');

        $this->post(route('staff.exam.call-next'))->assertRedirect(route('staff.exam.show', $visit));
        $this->post(route('staff.exam.skip', $visit));
        // Không còn ai khác: gọi tiếp sẽ gọi lại chính bệnh nhân này
        $this->assertSame(VisitStatus::Waiting, $visit->fresh()->status);
    }

    public function test_only_responsible_doctor_can_examine(): void
    {
        $visit = $this->walkIn();
        $owner = User::where('email', 'bs.quang@phongkham.test')->firstOrFail();

        // Bác sĩ khác: không gọi được, không ghi được bệnh án (BR-07), chỉ xem bản chỉ đọc
        $this->loginAs('bs.ha@phongkham.test');
        $this->post(route('staff.exam.call', $visit))->assertForbidden();
        app(QueueService::class)->call($visit, $owner);
        $this->put(route('staff.exam.clinical', $visit), ['pulse' => 80])->assertForbidden();
        $this->put(route('staff.exam.diagnoses', $visit), ['diagnoses' => [['icd10_code' => 'R51', 'name' => 'Đau đầu']]])->assertForbidden();
        $this->post(route('staff.exam.complete', $visit))->assertForbidden();
        $this->get(route('staff.exam.show', $visit))->assertRedirect(route('staff.records.show', $visit));

        // Lễ tân và KTV không vào được phân hệ khám
        $this->loginAs('letan@phongkham.test');
        $this->get(route('staff.exam.index'))->assertForbidden();
        $this->get(route('staff.schedule.index'))->assertForbidden();
        $this->get(route('staff.lab.index'))->assertForbidden();
        $this->get(route('staff.records.show', $visit))->assertForbidden();

        $this->loginAs('ktv@phongkham.test');
        $this->get(route('staff.exam.index'))->assertForbidden();
    }

    public function test_icd_and_medicine_search_return_json(): void
    {
        $this->loginAs('bs.quang@phongkham.test');

        $this->getJson(route('staff.icd10.search', ['q' => 'J0']))
            ->assertOk()->assertJsonStructure([['code', 'name']]);
        $this->getJson(route('staff.icd10.search', ['q' => '']))->assertOk()->assertExactJson([]);

        $this->getJson(route('staff.medicines.search', ['q' => 'para']))
            ->assertOk()
            ->assertJsonStructure([['id', 'name', 'full_name', 'active_ingredient', 'unit', 'price', 'stock']])
            ->assertJsonFragment(['name' => 'Panadol']);
    }

    public function test_schedule_and_leave_requests(): void
    {
        $this->loginAs('bs.quang@phongkham.test');
        $this->get(route('staff.schedule.index'))->assertOk()->assertSee('Ca làm việc cố định');
        $this->get(route('staff.schedule.index', ['week' => today()->addWeek()->toDateString(), 'day' => today()->addWeek()->toDateString()]))->assertOk();

        $this->get(route('staff.leave.index'))->assertOk();
        $this->post(route('staff.leave.store'), [
            'start_date' => today()->subDay()->toDateString(), 'end_date' => today()->toDateString(), 'reason' => 'x',
        ])->assertSessionHasErrors('start_date');

        $payload = ['start_date' => today()->addDays(3)->toDateString(), 'end_date' => today()->addDays(4)->toDateString(), 'reason' => 'Hội nghị'];
        $this->post(route('staff.leave.store'), $payload)->assertRedirect(route('staff.leave.index'));
        $leave = LeaveRequest::firstOrFail();
        $this->assertSame(LeaveStatus::Pending, $leave->status);
        // Trùng thời gian với yêu cầu đang chờ
        $this->post(route('staff.leave.store'), $payload)->assertSessionHas('error');
        $this->assertSame(1, LeaveRequest::count());

        $this->loginAs('bs.ha@phongkham.test');
        $this->delete(route('staff.leave.destroy', $leave))->assertForbidden();

        $this->loginAs('bs.quang@phongkham.test');
        $this->delete(route('staff.leave.destroy', $leave))->assertRedirect();
        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_admin_without_doctor_profile_sees_friendly_pages(): void
    {
        $this->loginAs('admin@phongkham.test');
        $this->get(route('staff.exam.index'))->assertOk()->assertSee('chưa có hồ sơ bác sĩ');
        $this->get(route('staff.schedule.index'))->assertOk();
        $this->get(route('staff.leave.index'))->assertOk();
        $this->post(route('staff.exam.call-next'))->assertForbidden();
    }
}
