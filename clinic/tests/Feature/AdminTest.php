<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Enums\VisitStatus;
use App\Models\Scheduling\Appointment;
use App\Models\Clinical\Diagnosis;
use App\Models\Catalog\Doctor;
use App\Models\Catalog\Icd10Code;
use App\Models\Billing\Invoice;
use App\Models\Scheduling\LeaveRequest;
use App\Models\Clinical\Patient;
use App\Models\Billing\Payment;
use App\Models\Clinical\Review;
use App\Models\Catalog\Room;
use App\Models\Catalog\Service;
use App\Models\Catalog\Specialty;
use App\Models\Scheduling\TimeSlot;
use App\Models\User;
use App\Models\Clinical\Visit;
use App\Models\Scheduling\WorkShift;
use App\Services\BookingService;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->loginAs('admin@phongkham.test');
    }

    public function test_all_admin_pages_render(): void
    {
        $specialty = Specialty::first();
        $room = Room::first();
        $doctor = Doctor::first();
        $service = Service::first();
        $staff = User::where('email', 'letan@phongkham.test')->first();

        $urls = [
            route('staff.admin.specialties.index'),
            route('staff.admin.specialties.index', ['q' => 'Nhi', 'status' => 'active']),
            route('staff.admin.specialties.create'),
            route('staff.admin.specialties.edit', $specialty),
            route('staff.admin.rooms.index', ['specialty_id' => $specialty->id]),
            route('staff.admin.rooms.create'),
            route('staff.admin.rooms.edit', $room),
            route('staff.admin.doctors.index', ['q' => 'Quang']),
            route('staff.admin.doctors.create'),
            route('staff.admin.doctors.edit', $doctor),
            route('staff.admin.services.index', ['type' => 'lab']),
            route('staff.admin.services.create'),
            route('staff.admin.services.edit', $service),
            route('staff.admin.icd10.index', ['q' => 'J02']),
            route('staff.admin.shifts.index'),
            route('staff.admin.shifts.index', ['doctor_id' => $doctor->id]),
            route('staff.admin.slots.index', ['date' => today()->addDay()->toDateString()]),
            route('staff.admin.leaves.index'),
            route('staff.admin.leaves.index', ['tab' => 'all']),
            route('staff.admin.staff.index', ['role' => 'receptionist']),
            route('staff.admin.staff.create'),
            route('staff.admin.staff.edit', $staff),
            route('staff.admin.roles.index'),
            route('staff.admin.patient-accounts.index', ['filter' => 'restricted']),
            route('staff.admin.settings.edit'),
            route('staff.admin.reviews.index'),
            route('staff.admin.reports.index'),
            route('staff.admin.audit.index'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_receptionist_cannot_access_admin_area(): void
    {
        $this->loginAs('letan@phongkham.test');

        $this->get(route('staff.admin.specialties.index'))->assertForbidden();
        $this->get(route('staff.admin.staff.index'))->assertForbidden();
        $this->get(route('staff.admin.reports.index'))->assertForbidden();
        $this->put(route('staff.admin.settings.update'), [])->assertForbidden();
    }

    public function test_specialty_crud(): void
    {
        $this->post(route('staff.admin.specialties.store'), [
            'name' => 'Nội tiết', 'icon' => 'fa-vial', 'keywords' => 'tiểu đường, tuyến giáp', 'sort_order' => 20, 'is_active' => 1,
        ])->assertRedirect(route('staff.admin.specialties.index'));

        $sp = Specialty::where('slug', 'noi-tiet')->firstOrFail();
        $this->assertTrue($sp->is_active);

        $this->put(route('staff.admin.specialties.update', $sp), ['name' => 'Nội tiết - Đái tháo đường', 'slug' => 'noi-tiet', 'sort_order' => 21])
            ->assertRedirect();
        $this->assertSame('Nội tiết - Đái tháo đường', $sp->fresh()->name);
        $this->assertFalse($sp->fresh()->is_active);

        // Trùng slug bị từ chối
        $this->post(route('staff.admin.specialties.store'), ['name' => 'Khác', 'slug' => 'noi-tiet'])->assertSessionHasErrors('slug');

        // Chưa dùng -> xóa hẳn; đang dùng -> chuyển ngừng hoạt động
        $this->delete(route('staff.admin.specialties.destroy', $sp))->assertRedirect();
        $this->assertModelMissing($sp);

        $used = Specialty::where('slug', 'nhi-khoa')->first();
        $this->delete(route('staff.admin.specialties.destroy', $used))->assertSessionHas('warning');
        $this->assertFalse($used->fresh()->is_active);
    }

    public function test_room_and_service_crud(): void
    {
        $this->post(route('staff.admin.rooms.store'), ['code' => 'P999', 'name' => 'Phòng thử', 'floor' => '3', 'is_active' => 1])->assertRedirect();
        $room = Room::where('code', 'P999')->firstOrFail();
        $this->put(route('staff.admin.rooms.update', $room), ['code' => 'P999', 'name' => 'Phòng thử 2', 'is_active' => 1])->assertRedirect();
        $this->assertSame('Phòng thử 2', $room->fresh()->name);
        $this->post(route('staff.admin.rooms.store'), ['code' => 'P999', 'name' => 'Trùng'])->assertSessionHasErrors('code');
        $this->delete(route('staff.admin.rooms.destroy', $room))->assertRedirect();
        $this->assertModelMissing($room);

        $this->post(route('staff.admin.services.store'), [
            'code' => 'XN99', 'name' => 'Xét nghiệm thử', 'type' => ServiceType::Lab->value, 'price' => 75000, 'unit' => 'lần', 'is_active' => 1,
        ])->assertRedirect();
        $service = Service::where('code', 'XN99')->firstOrFail();
        $this->assertSame(ServiceType::Lab, $service->type);
        $this->put(route('staff.admin.services.update', $service), [
            'code' => 'XN99', 'name' => 'Xét nghiệm thử', 'type' => 'imaging', 'price' => 90000, 'unit' => 'lần', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame(90000, $service->fresh()->price);
        $this->delete(route('staff.admin.services.destroy', $service))->assertRedirect();
        $this->assertModelMissing($service);
    }

    public function test_doctor_create_update_and_deactivate(): void
    {
        $specialty = Specialty::where('slug', 'da-lieu')->first();

        $this->post(route('staff.admin.doctors.store'), [
            'name' => 'Phan Thị Mới', 'email' => 'bs.moi@phongkham.test', 'phone' => '0912345670', 'password' => 'matkhau123',
            'specialty_id' => $specialty->id, 'title' => 'ThS.BS', 'consultation_fee' => 280000, 'experience_years' => 7, 'is_active' => 1,
        ])->assertRedirect(route('staff.admin.doctors.index'));

        $user = User::where('email', 'bs.moi@phongkham.test')->firstOrFail();
        $this->assertTrue($user->hasRole('doctor'));
        $doctor = $user->doctor;
        $this->assertSame(280000, $doctor->consultation_fee);

        $this->put(route('staff.admin.doctors.update', $doctor), [
            'name' => 'Phan Thị Mới', 'email' => 'bs.moi@phongkham.test', 'specialty_id' => $specialty->id,
            'consultation_fee' => 300000, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame(300000, $doctor->fresh()->consultation_fee);

        $this->delete(route('staff.admin.doctors.destroy', $doctor))->assertRedirect();
        $this->assertFalse($doctor->fresh()->is_active);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_icd10_store_import_and_delete(): void
    {
        $this->post(route('staff.admin.icd10.store'), ['code' => 'x99.9', 'name' => 'Bệnh thử nghiệm'])->assertRedirect();
        $this->assertDatabaseHas('icd10_codes', ['code' => 'X99.9']);

        $csv = "\xEF\xBB\xBFcode,name,name_en,chapter\nY01,Bệnh Y một,Disease Y1,Chương thử\nJ00,Viêm mũi họng cấp (cập nhật),,\n,thiếu mã\n";
        $this->post(route('staff.admin.icd10.import'), ['file' => UploadedFile::fake()->createWithContent('icd.csv', $csv)])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('icd10_codes', ['code' => 'Y01', 'name' => 'Bệnh Y một', 'chapter' => 'Chương thử']);
        $this->assertSame('Viêm mũi họng cấp (cập nhật)', Icd10Code::where('code', 'J00')->value('name'));

        $code = Icd10Code::where('code', 'X99.9')->first();
        $this->delete(route('staff.admin.icd10.destroy', $code))->assertRedirect();
        $this->assertModelMissing($code);
    }

    public function test_work_shift_management_and_slot_generation(): void
    {
        $doctor = Doctor::whereHas('specialty', fn ($q) => $q->where('slug', 'mat'))->first();

        $this->post(route('staff.admin.shifts.store'), [
            'doctor_id' => $doctor->id, 'weekday' => 0, 'start_time' => '08:00', 'end_time' => '10:00',
            'slot_minutes' => 20, 'max_patients_per_slot' => 3, 'is_active' => 1,
        ])->assertRedirect();
        $shift = WorkShift::where('doctor_id', $doctor->id)->where('weekday', 0)->firstOrFail();

        // Trùng giờ bị từ chối, giờ kết thúc phải sau giờ bắt đầu
        $this->post(route('staff.admin.shifts.store'), [
            'doctor_id' => $doctor->id, 'weekday' => 0, 'start_time' => '09:00', 'end_time' => '11:00', 'slot_minutes' => 30, 'max_patients_per_slot' => 2,
        ])->assertSessionHasErrors('start_time');
        $this->post(route('staff.admin.shifts.store'), [
            'doctor_id' => $doctor->id, 'weekday' => 0, 'start_time' => '12:00', 'end_time' => '11:00', 'slot_minutes' => 30, 'max_patients_per_slot' => 2,
        ])->assertSessionHasErrors('end_time');

        $this->post(route('staff.admin.shifts.generate'), ['days' => 14, 'doctor_id' => $doctor->id])->assertSessionHas('success');
        $sundaySlots = TimeSlot::where('work_shift_id', $shift->id)->count();
        $this->assertSame(12, $sundaySlots); // 2 Chủ nhật × 6 slot 20 phút

        $this->put(route('staff.admin.shifts.update', $shift), [
            'weekday' => 0, 'start_time' => '08:00', 'end_time' => '11:00', 'slot_minutes' => 30, 'max_patients_per_slot' => 2, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame('11:00', substr($shift->fresh()->end_time, 0, 5));

        $this->put(route('staff.admin.shifts.update', $shift), ['toggle' => 1])->assertRedirect();
        $this->assertFalse($shift->fresh()->is_active);

        $slot = TimeSlot::where('work_shift_id', $shift->id)->first();
        $this->post(route('staff.admin.slots.toggle', $slot))->assertRedirect();
        $this->assertFalse($slot->fresh()->is_active);
        $this->get(route('staff.admin.slots.index', ['date' => $slot->date->toDateString(), 'doctor_id' => $doctor->id]))->assertOk()->assertSee($slot->timeLabel());

        $this->delete(route('staff.admin.shifts.destroy', $shift))->assertRedirect();
        $this->assertModelMissing($shift);
    }

    public function test_leave_approval_reassign_and_cancel_affected_appointments(): void
    {
        $noi = Specialty::where('slug', 'noi-tong-quat')->first();
        [$doctorA, $doctorB] = Doctor::where('specialty_id', $noi->id)->orderBy('id')->take(2)->get();

        $slotA = TimeSlot::where('doctor_id', $doctorA->id)->whereDate('date', '>', today())
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('time_slots as b')
                ->whereColumn('b.starts_at', 'time_slots.starts_at')->where('b.doctor_id', $doctorB->id))
            ->orderBy('starts_at')->firstOrFail();

        $booking = app(BookingService::class);
        $receptionist = User::where('email', 'letan@phongkham.test')->first();
        $patients = Patient::whereNotNull('user_id')->take(2)->get();
        $a1 = $booking->bookByStaff($receptionist, $patients[0], $slotA, 'Khám tổng quát');
        $a2 = $booking->bookByStaff($receptionist, $patients[1], $slotA, 'Đau dạ dày');

        $leave = LeaveRequest::create([
            'doctor_id' => $doctorA->id, 'start_date' => $slotA->date, 'end_date' => $slotA->date,
            'reason' => 'Đi hội nghị', 'status' => LeaveStatus::Pending,
        ]);

        $this->get(route('staff.admin.leaves.show', $leave))->assertOk()->assertSee($a1->code)->assertSee($doctorB->display_name);

        $this->post(route('staff.admin.leaves.approve', $leave), ['note' => 'Đồng ý'])->assertRedirect(route('staff.admin.leaves.show', $leave));
        $this->assertSame(LeaveStatus::Approved, $leave->fresh()->status);
        $this->assertSame(0, TimeSlot::where('doctor_id', $doctorA->id)->whereDate('date', $slotA->date)->where('is_active', true)->count());

        $slotB = TimeSlot::where('doctor_id', $doctorB->id)->where('starts_at', $slotA->starts_at)->first();
        $this->post(route('staff.admin.leaves.reassign', $a1), ['time_slot_id' => $slotB->id])->assertSessionHas('success');
        $this->assertSame(AppointmentStatus::Rescheduled, $a1->fresh()->status);
        $this->assertSame($doctorB->id, Appointment::find($a1->fresh()->rescheduled_to_id)->doctor_id);

        $this->post(route('staff.admin.leaves.cancel-appointment', $a2))->assertSessionHas('success');
        $this->assertSame(AppointmentStatus::Cancelled, $a2->fresh()->status);
        $this->assertFalse($a2->fresh()->is_late_cancel);

        // Từ chối một yêu cầu khác
        $other = LeaveRequest::create([
            'doctor_id' => $doctorB->id, 'start_date' => today()->addDays(20), 'end_date' => today()->addDays(21),
            'reason' => 'Việc riêng', 'status' => LeaveStatus::Pending,
        ]);
        $this->post(route('staff.admin.leaves.reject', $other), ['note' => 'Thiếu người'])->assertRedirect();
        $this->assertSame(LeaveStatus::Rejected, $other->fresh()->status);
    }

    public function test_staff_account_management(): void
    {
        $this->post(route('staff.admin.staff.store'), [
            'name' => 'Lễ tân mới', 'email' => 'letan3@phongkham.test', 'phone' => '0900000099', 'employee_code' => 'NV099',
            'password' => 'matkhau123', 'password_confirmation' => 'matkhau123',
            'roles' => ['receptionist', 'cashier'], 'is_active' => 1,
        ])->assertRedirect(route('staff.admin.staff.index'));

        $user = User::where('email', 'letan3@phongkham.test')->firstOrFail();
        $this->assertTrue($user->hasAllRoles(['receptionist', 'cashier']));

        $this->put(route('staff.admin.staff.update', $user), [
            'name' => 'Lễ tân mới', 'email' => 'letan3@phongkham.test', 'roles' => ['pharmacist'], 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame(['pharmacist'], $user->fresh()->getRoleNames()->all());

        $this->post(route('staff.admin.staff.toggle', $user), ['reason' => 'Nghỉ phép dài hạn'])->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame('Nghỉ phép dài hạn', $user->fresh()->locked_reason);
        $this->post(route('staff.admin.staff.toggle', $user))->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);

        // Không thể tự khóa chính mình
        $this->post(route('staff.admin.staff.toggle', $this->admin))->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->is_active);

        // Tạo nhân viên vai trò bác sĩ -> chuyển sang tạo hồ sơ bác sĩ
        $this->post(route('staff.admin.staff.store'), [
            'name' => 'Bác sĩ chưa hồ sơ', 'email' => 'bs.new@phongkham.test', 'password' => 'matkhau123', 'password_confirmation' => 'matkhau123',
            'roles' => ['doctor'], 'is_active' => 1,
        ])->assertRedirectContains('/quan-tri/bac-si/create');

        $this->post(route('staff.admin.staff.store'), ['name' => 'Thiếu vai trò', 'email' => 'x@phongkham.test', 'password' => 'matkhau123', 'password_confirmation' => 'matkhau123'])
            ->assertSessionHasErrors('roles');
    }

    public function test_role_matrix_update(): void
    {
        $permissions = [
            'receptionist' => ['appointments.view', 'appointments.manage', 'reception.manage', 'billing.manage'],
            'doctor' => ['medical.examine', 'medical.view'],
            'lab_technician' => ['lab.manage'],
            'cashier' => ['billing.manage'],
            'pharmacist' => ['pharmacy.dispense', 'inventory.manage'],
            'admin' => [],
        ];

        $this->put(route('staff.admin.roles.update'), ['permissions' => $permissions])->assertSessionHas('success');

        $this->assertTrue(Role::findByName('receptionist')->hasPermissionTo('billing.manage'));
        $this->assertFalse(Role::findByName('receptionist')->hasPermissionTo('patients.manage'));
        $this->assertFalse(Role::findByName('doctor')->hasPermissionTo('leave.request'));
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('audit.view')); // admin không bị đổi
    }

    public function test_patient_account_reset_no_show_and_lock(): void
    {
        $user = User::where('email', 'benhnhan9@phongkham.test')->firstOrFail();
        $user->update(['no_show_count' => 4]);

        $this->post(route('staff.admin.patient-accounts.reset-no-show', $user))->assertSessionHas('success');
        $this->assertSame(0, $user->fresh()->no_show_count);
        $this->assertDatabaseHas('activity_log', ['description' => 'Gỡ hạn chế no-show cho tài khoản bệnh nhân', 'subject_id' => $user->id]);

        $this->post(route('staff.admin.patient-accounts.toggle', $user), ['reason' => 'Đặt lịch ảo'])->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
        $this->post(route('staff.admin.patient-accounts.toggle', $user))->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_settings_update_changes_business_rules(): void
    {
        $payload = app(Settings::class)->all();
        $payload['booking_min_hours_before'] = 5;
        $payload['no_show_policy'] = 'block';
        $payload['auto_confirm_online'] = 1;

        $this->put(route('staff.admin.settings.update'), $payload)->assertSessionHas('success');

        $this->assertSame(5, setting('booking_min_hours_before'));
        $this->assertSame('block', setting('no_show_policy'));
        $this->assertTrue(setting('auto_confirm_online'));

        $payload['no_show_policy'] = 'invalid';
        $this->put(route('staff.admin.settings.update'), $payload)->assertSessionHasErrors('no_show_policy');
    }

    public function test_review_toggle_recalculates_rating(): void
    {
        $visit = $this->completedVisit();
        $review = Review::create([
            'visit_id' => $visit->id, 'doctor_id' => $visit->doctor_id, 'patient_id' => $visit->patient_id,
            'user_id' => $visit->patient->user_id ?? $this->admin->id, 'rating' => 2, 'comment' => 'Nội dung vi phạm',
        ]);
        $this->assertSame(1, $visit->doctor->fresh()->rating_count);

        $this->get(route('staff.admin.reviews.index', ['rating' => 2]))->assertOk()->assertSee('Nội dung vi phạm');
        $this->post(route('staff.admin.reviews.toggle', $review))->assertSessionHas('success');
        $this->assertTrue($review->fresh()->is_hidden);
        $this->assertSame(0, $visit->doctor->fresh()->rating_count);
    }

    public function test_reports_page_and_exports(): void
    {
        $this->completedVisit(paid: true);

        $this->get(route('staff.admin.reports.index'))->assertOk()->assertSee('Hiệu suất bác sĩ')->assertSee('J02.9');
        $this->get(route('staff.admin.reports.index', ['from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString(), 'specialty_id' => Specialty::first()->id]))->assertOk();

        $csv = $this->get(route('staff.admin.reports.export', ['format' => 'csv']));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('HIỆU SUẤT BÁC SĨ', $content);

        $pdf = $this->get(route('staff.admin.reports.export', ['format' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
    }

    public function test_audit_log_with_filters(): void
    {
        $this->post(route('staff.admin.rooms.store'), ['code' => 'P777', 'name' => 'Phòng nhật ký', 'is_active' => 1]);

        $this->get(route('staff.admin.audit.index'))->assertOk()->assertSee('Phòng nhật ký');
        $this->get(route('staff.admin.audit.index', [
            'log_name' => 'catalog', 'event' => 'created', 'user' => 'admin', 'subject_type' => 'Room',
            'from' => today()->toDateString(), 'to' => today()->toDateString(),
        ]))->assertOk()->assertSee('Room');
    }

    // ------------------------------------------------------------------

    protected function completedVisit(bool $paid = false): Visit
    {
        $doctor = Doctor::with('specialty')->first();
        $patient = Patient::whereNotNull('user_id')->first();

        $visit = Visit::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'specialty_id' => $doctor->specialty_id, 'room_id' => $doctor->room_id,
            'visit_date' => today(), 'queue_number' => 1, 'queue_sort' => now()->subHour(), 'status' => VisitStatus::Completed,
            'checked_in_at' => now()->subHour(), 'started_at' => now()->subMinutes(40), 'completed_at' => now()->subMinutes(20), 'locked_at' => now(),
        ]);
        Diagnosis::create(['visit_id' => $visit->id, 'icd10_code' => 'J02.9', 'name' => 'Viêm họng cấp, không đặc hiệu', 'is_primary' => true]);

        if ($paid) {
            $invoice = Invoice::create([
                'visit_id' => $visit->id, 'patient_id' => $patient->id, 'status' => InvoiceStatus::Paid,
                'subtotal' => 350000, 'total' => 350000, 'paid_amount' => 350000, 'payment_method' => PaymentMethod::Cash, 'paid_at' => now(),
            ]);
            $invoice->items()->create(['item_type' => 'exam', 'description' => 'Phí khám', 'quantity' => 1, 'unit_price' => 250000, 'amount' => 250000]);
            $invoice->items()->create(['item_type' => 'medicine', 'description' => 'Thuốc', 'quantity' => 10, 'unit_price' => 10000, 'amount' => 100000]);
            Payment::create([
                'invoice_id' => $invoice->id, 'patient_id' => $patient->id, 'type' => 'payment', 'method' => PaymentMethod::Cash,
                'amount' => 350000, 'status' => PaymentStatus::Success, 'paid_at' => now(),
            ]);
        }

        return $visit->load(['doctor', 'patient']);
    }
}
