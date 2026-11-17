<?php

namespace Tests\Feature;

use App\Enums\PrescriptionStatus;
use App\Enums\ServiceOrderStatus;
use App\Enums\VisitStatus;
use App\Models\Catalog\Doctor;
use App\Models\Clinical\LabResult;
use App\Models\Pharmacy\Medicine;
use App\Models\Clinical\Patient;
use App\Models\Clinical\Prescription;
use App\Models\Catalog\Service;
use App\Models\Clinical\ServiceOrder;
use App\Models\User;
use App\Models\Clinical\Visit;
use App\Notifications\GeneralNotification;
use App\Services\BillingService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    protected function demoPatient(): Patient
    {
        return Patient::where('full_name', 'Nguyễn Văn An')->firstOrFail();
    }

    /** Lượt khám đã hoàn thành có chẩn đoán, CLS, đơn thuốc và hóa đơn. */
    protected function makeCompletedVisit(Patient $patient): Visit
    {
        $doctor = Doctor::with('user')->firstOrFail();

        $visit = Visit::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $doctor->specialty_id,
            'room_id' => $doctor->room_id,
            'visit_date' => today(),
            'queue_number' => 1,
            'queue_sort' => now(),
            'status' => VisitStatus::Completed,
            'type' => 'walk_in',
            'checked_in_at' => now()->subHour(),
            'started_at' => now()->subMinutes(40),
            'completed_at' => now()->subMinutes(10),
            'locked_at' => now()->subMinutes(10),
            'reason' => 'Sốt, đau họng',
            'pulse' => 82, 'bp_systolic' => 120, 'bp_diastolic' => 80, 'temperature' => 37.8, 'weight' => 65, 'height' => 170,
            'conclusion' => 'Viêm họng cấp',
            'advice' => 'Uống nhiều nước',
            'follow_up_date' => today()->addDays(7),
        ]);
        $visit->diagnoses()->create(['icd10_code' => 'J02.9', 'name' => 'Viêm họng cấp, không đặc hiệu', 'is_primary' => true]);

        $service = Service::where('code', 'XN01')->first();
        $order = ServiceOrder::create([
            'visit_id' => $visit->id, 'service_id' => $service->id, 'ordered_by' => $doctor->user_id,
            'price' => $service->price, 'status' => ServiceOrderStatus::Completed,
        ]);
        Storage::disk('local')->put('lab-results/test-result.txt', 'WBC 7.2');
        LabResult::create([
            'service_order_id' => $order->id, 'result' => "WBC: 7.2 G/L\nHGB: 140 g/L", 'conclusion' => 'Bình thường',
            'attachment_path' => 'lab-results/test-result.txt', 'attachment_name' => 'ket-qua.txt', 'completed_at' => now(),
        ]);

        $medicine = Medicine::where('code', 'T001')->first();
        $prescription = Prescription::create([
            'visit_id' => $visit->id, 'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'status' => PrescriptionStatus::Pending,
        ]);
        $prescription->items()->create([
            'medicine_id' => $medicine->id, 'quantity' => 10, 'dosage' => '1 viên/lần', 'usage' => 'Uống sau ăn',
            'days' => 5, 'unit_price' => $medicine->price, 'amount' => 10 * $medicine->price,
        ]);

        app(BillingService::class)->createForVisit($visit);

        return $visit->fresh();
    }

    public function test_dashboard_profile_and_notifications(): void
    {
        $user = $this->loginAs('benhnhan@phongkham.test');
        $user->notify(new GeneralNotification('Kiểm thử', 'Nội dung thông báo kiểm thử', route('account.appointments.index')));

        $this->get(route('account.dashboard'))->assertOk()->assertSee('Xin chào')->assertSee('Kiểm thử');
        $this->get(route('account.profile.edit'))->assertOk();

        $this->put(route('account.profile.update'), ['name' => 'Nguyễn Văn An Mới', 'email' => $user->email, 'phone' => $user->phone])
            ->assertSessionHas('success');
        $this->assertSame('Nguyễn Văn An Mới', $user->fresh()->name);

        // Trùng email người khác
        $this->put(route('account.profile.update'), ['name' => 'X', 'email' => 'benhnhan1@phongkham.test', 'phone' => $user->phone])
            ->assertSessionHasErrors('email');

        $this->put(route('account.profile.password'), ['current_password' => 'sai', 'password' => 'matkhau123', 'password_confirmation' => 'matkhau123'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('account.profile.password'), ['current_password' => 'password', 'password' => 'matkhau123', 'password_confirmation' => 'matkhau123'])
            ->assertSessionHas('success');

        $this->get(route('account.notifications.index'))->assertOk()->assertSee('Nội dung thông báo kiểm thử');
        $notification = $user->notifications()->first();
        $this->post(route('account.notifications.read', $notification->id))->assertRedirect(route('account.appointments.index', [], false));
        $this->assertNotNull($notification->fresh()->read_at);

        $user->notify(new GeneralNotification('Lần 2', 'Thông báo thứ hai'));
        $this->post(route('account.notifications.read-all'))->assertSessionHas('success');
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_family_profiles_crud(): void
    {
        $user = $this->loginAs('benhnhan@phongkham.test');

        $this->get(route('account.patients.index'))->assertOk()->assertSee('Lê Thị Bình');
        $this->get(route('account.patients.create'))->assertOk();

        $this->post(route('account.patients.store'), [
            'full_name' => 'Nguyễn Thị Mai', 'relationship' => 'Con', 'date_of_birth' => '2018-06-01', 'gender' => 'female',
            'allergies' => 'Hải sản',
        ])->assertRedirect(route('account.patients.index'));
        $child = Patient::where('full_name', 'Nguyễn Thị Mai')->firstOrFail();
        $this->assertSame($user->id, $child->user_id);
        $this->assertSame('Hải sản', $child->allergies);

        $this->get(route('account.patients.edit', $child))->assertOk();
        $this->put(route('account.patients.update', $child), [
            'full_name' => 'Nguyễn Thị Mai Anh', 'relationship' => 'Con', 'date_of_birth' => '2018-06-01', 'gender' => 'female',
        ])->assertRedirect(route('account.patients.index'));
        $this->assertSame('Nguyễn Thị Mai Anh', $child->fresh()->full_name);

        $this->delete(route('account.patients.destroy', $child))->assertSessionHas('success');
        $this->assertSoftDeleted($child);

        // Không xóa được hồ sơ "Bản thân"
        $this->delete(route('account.patients.destroy', $this->demoPatient()))->assertSessionHas('error');
        $this->assertNotSoftDeleted($this->demoPatient());

        // BR-09: không sửa được hồ sơ của tài khoản khác
        $other = User::where('email', 'benhnhan1@phongkham.test')->first()->patients()->first();
        $this->get(route('account.patients.edit', $other))->assertForbidden();
        $this->delete(route('account.patients.destroy', $other))->assertForbidden();
    }

    public function test_appointment_list_tabs(): void
    {
        $this->loginAs('benhnhan@phongkham.test');

        foreach (['sap-toi', 'da-qua', 'tat-ca'] as $tab) {
            $this->get(route('account.appointments.index', ['tab' => $tab]))->assertOk();
        }
        $this->get(route('account.appointments.index', ['patient_id' => $this->demoPatient()->id]))->assertOk();
    }

    public function test_visit_history_detail_review_and_attachment(): void
    {
        $user = $this->loginAs('benhnhan@phongkham.test');
        $visit = $this->makeCompletedVisit($this->demoPatient());

        $this->get(route('account.visits.index'))->assertOk()->assertSee($visit->code);
        $this->get(route('account.visits.show', $visit))->assertOk()
            ->assertSee('Viêm họng cấp')
            ->assertSee('Panadol')
            ->assertSee('WBC: 7.2');

        // BR-08: xem hồ sơ y tế được ghi audit log
        $this->assertDatabaseHas('activity_log', ['subject_id' => $visit->id, 'event' => 'viewed', 'causer_id' => $user->id]);

        $result = $visit->serviceOrders()->first()->result;
        $this->get(route('account.lab-results.attachment', $result))->assertOk();

        $this->post(route('account.visits.review', $visit), ['rating' => 5, 'comment' => 'Bác sĩ rất tận tâm'])->assertSessionHas('success');
        $this->assertDatabaseHas('reviews', ['visit_id' => $visit->id, 'rating' => 5]);
        $this->assertEquals(5.0, $visit->doctor->fresh()->rating_avg);

        // Không đánh giá 2 lần
        $this->post(route('account.visits.review', $visit), ['rating' => 1])->assertSessionHas('error');
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_invoices_list_detail_pdf_and_online_payment(): void
    {
        $this->loginAs('benhnhan@phongkham.test');
        $visit = $this->makeCompletedVisit($this->demoPatient());
        $invoice = $visit->invoice;
        $this->assertGreaterThan(0, $invoice->total);

        $this->get(route('account.invoices.index'))->assertOk()->assertSee($invoice->code);
        $this->get(route('account.invoices.show', $invoice))->assertOk()->assertSee('Thanh toán online');

        $pdf = $this->get(route('account.invoices.pdf', $invoice));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));

        $this->post(route('account.invoices.pay', $invoice))->assertRedirect();
        $payment = \App\Models\Billing\Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->post(route('payment.simulate.process', $payment), ['action' => 'success'])->assertOk();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status->value);
        $this->assertSame((int) $invoice->total, (int) $invoice->paid_amount);
    }

    public function test_br09_cannot_view_other_accounts_records(): void
    {
        $other = User::where('email', 'benhnhan1@phongkham.test')->first()->patients()->first();
        $visit = $this->makeCompletedVisit($other);

        $this->loginAs('benhnhan@phongkham.test');
        $this->get(route('account.visits.show', $visit))->assertForbidden();
        $this->post(route('account.visits.review', $visit), ['rating' => 5])->assertForbidden();
        $this->get(route('account.invoices.show', $visit->invoice))->assertForbidden();
        $this->get(route('account.invoices.pdf', $visit->invoice))->assertForbidden();
        $this->get(route('account.lab-results.attachment', $visit->serviceOrders()->first()->result))->assertForbidden();
        $this->post(route('account.invoices.pay', $visit->invoice))->assertForbidden();
    }

    public function test_queue_endpoint_returns_people_ahead(): void
    {
        $staff = User::where('email', 'letan@phongkham.test')->first();
        $slot = \App\Models\Scheduling\TimeSlot::where('is_active', true)->where('starts_at', '>', now()->addHours(3))->orderBy('starts_at')->firstOrFail();
        $appointment = app(\App\Services\BookingService::class)->bookByStaff($staff, $this->demoPatient(), $slot);

        $this->loginAs('benhnhan@phongkham.test');
        $this->getJson(route('account.appointments.queue', $appointment))
            ->assertOk()
            ->assertJson(['status' => 'confirmed', 'queue_number' => null]);

        // Giả lập đã check-in hôm nay
        Visit::create([
            'appointment_id' => $appointment->id, 'patient_id' => $appointment->patient_id, 'doctor_id' => $appointment->doctor_id,
            'specialty_id' => $appointment->specialty_id, 'visit_date' => today(), 'queue_number' => 3, 'queue_sort' => now(),
            'status' => VisitStatus::Waiting,
        ]);
        $this->getJson(route('account.appointments.queue', $appointment))
            ->assertOk()
            ->assertJson(['status' => 'waiting', 'queue_number' => 3, 'people_ahead' => 0]);

        $this->get(route('account.appointments.show', $appointment))->assertOk()->assertSee('Theo dõi hàng đợi');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
        $this->get(route('account.booking.create'))->assertRedirect(route('login'));
    }
}
