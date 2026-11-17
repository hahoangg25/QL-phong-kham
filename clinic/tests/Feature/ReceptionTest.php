<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Models\Scheduling\Appointment;
use App\Models\Catalog\Doctor;
use App\Models\Clinical\Patient;
use App\Models\Scheduling\TimeSlot;
use App\Models\User;
use App\Models\Clinical\Visit;
use App\Notifications\GeneralNotification;
use App\Services\BookingService;
use Carbon\Carbon;
use Tests\TestCase;

class ReceptionTest extends TestCase
{
    protected Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        // Đưa "hôm nay" về 07:00 của ngày làm việc đầu tiên có slot, để mọi slot trong ngày còn ở tương lai
        $first = substr((string) TimeSlot::min('date'), 0, 10);
        $this->travelTo(Carbon::parse($first.' 07:00:00'));

        $this->doctor = Doctor::with('user')->whereHas('user', fn ($q) => $q->where('email', 'bs.quang@phongkham.test'))->first();
    }

    protected function slot(?Doctor $doctor = null, int $skip = 0): TimeSlot
    {
        return TimeSlot::where('doctor_id', ($doctor ?? $this->doctor)->id)
            ->whereDate('date', today())->orderBy('start_time')->skip($skip)->firstOrFail();
    }

    protected function bookToday(int $skip = 0, ?Patient $patient = null): Appointment
    {
        $staff = User::where('email', 'letan@phongkham.test')->first();
        $patient ??= Patient::whereNull('user_id')->first();

        return app(BookingService::class)->bookByStaff($staff, $patient, $this->slot(null, $skip), 'Sốt nhẹ');
    }

    public function test_dashboard_renders_for_every_role(): void
    {
        $appointment = $this->bookToday();
        $this->bookToday(1, Patient::whereNull('user_id')->skip(1)->first())->update(['status' => AppointmentStatus::Pending]);
        app(\App\Services\QueueService::class)->checkIn($appointment, User::where('email', 'letan@phongkham.test')->first());

        foreach (['admin', 'letan', 'bs.quang', 'ktv', 'thungan', 'duocsi'] as $who) {
            $this->loginAs($who.'@phongkham.test');
            $this->get(route('staff.dashboard'))->assertOk()->assertSee('Xin chào');
        }
    }

    public function test_notifications_and_profile(): void
    {
        $user = $this->loginAs('letan@phongkham.test');
        $user->notify(new GeneralNotification('Thông báo thử', 'Nội dung thử', route('staff.queue.index')));
        $notification = $user->notifications()->first();

        $this->get(route('staff.notifications.index'))->assertOk()->assertSee('Thông báo thử');
        $this->post(route('staff.notifications.read', $notification->id))->assertRedirect('/quan-ly/hang-doi');
        $this->assertNotNull($notification->fresh()->read_at);
        $this->post(route('staff.notifications.read-all'))->assertRedirect();

        $this->get(route('staff.profile.edit'))->assertOk();
        $this->put(route('staff.profile.update'), ['name' => 'Nguyễn Thị Hoa Mới', 'email' => $user->email, 'phone' => $user->phone])
            ->assertSessionHasNoErrors();
        $this->assertSame('Nguyễn Thị Hoa Mới', $user->fresh()->name);
    }

    public function test_appointment_pages_and_staff_booking(): void
    {
        $this->loginAs('letan@phongkham.test');
        $appointment = $this->bookToday();

        $this->get(route('staff.appointments.index'))->assertOk()->assertSee($appointment->code);
        $this->get(route('staff.appointments.index', ['date' => '', 'q' => $appointment->patient->full_name]))->assertOk()->assertSee($appointment->code);
        $this->get(route('staff.appointments.show', $appointment))->assertOk()->assertSee('Lịch sử trạng thái');
        $this->get(route('staff.appointments.create'))->assertOk();

        // LT-05: đặt hộ cho bệnh nhân có sẵn
        $patient = Patient::whereNull('user_id')->skip(1)->first();
        $this->post(route('staff.appointments.store'), [
            'patient_id' => $patient->id,
            'time_slot_id' => $this->slot(null, 1)->id,
            'symptoms' => 'Đau đầu',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AppointmentStatus::Confirmed, $patient->appointments()->first()->status);

        // LT-05: đặt hộ cho bệnh nhân mới (tạo nhanh hồ sơ)
        $this->post(route('staff.appointments.store'), [
            'full_name' => 'Bệnh Nhân Gọi Điện',
            'phone' => '0988777666',
            'time_slot_id' => $this->slot(null, 2)->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('patients', ['full_name' => 'Bệnh Nhân Gọi Điện', 'phone' => '0988777666']);

        // BR-03: cùng bệnh nhân, cùng chuyên khoa, cùng ngày -> bị chặn
        $this->post(route('staff.appointments.store'), [
            'patient_id' => $patient->id,
            'time_slot_id' => $this->slot(null, 3)->id,
        ])->assertSessionHas('error');
    }

    public function test_confirm_reject_cancel_and_reschedule(): void
    {
        $this->loginAs('letan@phongkham.test');

        $pending = $this->bookToday(0);
        $pending->update(['status' => AppointmentStatus::Pending]);
        $this->post(route('staff.appointments.confirm', $pending))->assertRedirect();
        $this->assertSame(AppointmentStatus::Confirmed, $pending->fresh()->status);

        $toReject = $this->bookToday(1, Patient::whereNull('user_id')->skip(1)->first());
        $toReject->update(['status' => AppointmentStatus::Pending]);
        $this->post(route('staff.appointments.reject', $toReject), [])->assertSessionHasErrors('reason');
        $this->post(route('staff.appointments.reject', $toReject), ['reason' => 'Bác sĩ kín lịch'])->assertRedirect();
        $this->assertSame(AppointmentStatus::Cancelled, $toReject->fresh()->status);

        $toCancel = $this->bookToday(2, Patient::whereNull('user_id')->skip(2)->first());
        $slot = $toCancel->timeSlot;
        $this->post(route('staff.appointments.cancel', $toCancel), ['reason' => 'Bệnh nhân báo bận'])->assertRedirect();
        $toCancel->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $toCancel->status);
        $this->assertFalse($toCancel->is_late_cancel);
        $this->assertSame(0, $slot->fresh()->booked_count);

        // LT-06: đổi lịch hộ -> lịch cũ "Đã đổi lịch", lịch mới đã xác nhận
        $this->get(route('staff.appointments.reschedule', $pending))->assertOk();
        $newSlot = $this->slot(null, 5);
        $this->post(route('staff.appointments.reschedule.store', $pending), ['time_slot_id' => $newSlot->id])->assertRedirect();
        $pending->refresh();
        $this->assertSame(AppointmentStatus::Rescheduled, $pending->status);
        $this->assertSame($newSlot->id, $pending->rescheduledTo->time_slot_id);
        $this->get(route('staff.appointments.show', $pending))->assertOk();
    }

    public function test_checkin_lookup_and_ticket(): void
    {
        $this->loginAs('letan@phongkham.test');
        $appointment = $this->bookToday();

        $this->get(route('staff.reception.checkin'))->assertOk()->assertSee($appointment->code);
        $this->post(route('staff.reception.lookup'), ['q' => $appointment->code])
            ->assertRedirect(route('staff.reception.checkin', ['q' => $appointment->code]));
        $this->get(route('staff.reception.checkin', ['q' => $appointment->code]))->assertOk()->assertSee('cấp số thứ tự');
        $this->get(route('staff.reception.checkin', ['q' => $appointment->patient->phone]))->assertOk()->assertSee($appointment->code);

        $this->post(route('staff.reception.checkin.store', $appointment))
            ->assertRedirect()->assertSessionHas('ticket_url');

        $visit = Visit::where('appointment_id', $appointment->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame(1, $visit->queue_number);
        $this->assertSame(VisitStatus::Waiting, $visit->status);
        $this->assertSame(AppointmentStatus::CheckedIn, $appointment->fresh()->status);

        $this->get(route('staff.queue.ticket', $visit))->assertOk()->assertSee('Phiếu số thứ tự khám bệnh');

        // Check-in lần hai bị chặn
        $this->post(route('staff.reception.checkin.store', $appointment))->assertSessionHas('error');
    }

    public function test_walk_in_and_queue_actions(): void
    {
        $this->loginAs('letan@phongkham.test');
        $this->get(route('staff.reception.walkin'))->assertOk();

        // Bệnh nhân mới
        $this->post(route('staff.reception.walkin.store'), [
            'full_name' => 'Khách Vãng Lai', 'phone' => '0977111222', 'date_of_birth' => '1945-02-02', 'gender' => 'male',
            'doctor_id' => $this->doctor->id, 'reason' => 'Ho', 'priority' => 1,
        ])->assertRedirect(route('staff.reception.walkin'))->assertSessionHas('ticket_url');
        $first = Visit::whereHas('patient', fn ($q) => $q->where('full_name', 'Khách Vãng Lai'))->first();
        $this->assertTrue($first->is_priority);
        $this->assertSame('walk_in', $first->type);

        // Bệnh nhân có sẵn
        $patient = Patient::whereNull('user_id')->first();
        $this->post(route('staff.reception.walkin.store'), ['patient_id' => $patient->id, 'doctor_id' => $this->doctor->id])
            ->assertSessionHasNoErrors();
        $second = Visit::where('patient_id', $patient->id)->first();
        $this->assertSame(2, $second->queue_number);

        $this->get(route('staff.queue.index'))->assertOk()->assertSee($this->doctor->display_name);
        $this->get(route('staff.queue.index', ['doctor_id' => $this->doctor->id]))->assertOk()->assertSee('Khách Vãng Lai');
        $this->getJson(route('staff.queue.data', ['doctor_id' => $this->doctor->id]))->assertOk()->assertJsonStructure(['html', 'updated_at']);
        $this->getJson(route('staff.queue.data'))->assertOk();

        $this->post(route('staff.queue.priority', $first))->assertRedirect();
        $this->assertFalse($first->fresh()->is_priority);
        $this->post(route('staff.queue.move-to-end', $first))->assertRedirect();

        $other = Doctor::where('id', '!=', $this->doctor->id)->first();
        $this->post(route('staff.queue.transfer', $second), ['doctor_id' => $other->id])->assertRedirect();
        $this->assertSame($other->id, $second->fresh()->doctor_id);
        $this->assertSame(1, $second->fresh()->queue_number);

        $this->post(route('staff.queue.cancel', $first), [])->assertRedirect();
        $this->assertSame(VisitStatus::Cancelled, $first->fresh()->status);
    }

    public function test_patient_management(): void
    {
        $this->loginAs('letan@phongkham.test');
        $patient = Patient::whereNotNull('user_id')->first();

        $this->get(route('staff.patients.index'))->assertOk();
        $this->get(route('staff.patients.index', ['q' => $patient->code]))->assertOk()->assertSee($patient->full_name);
        $this->getJson(route('staff.patients.search', ['q' => mb_substr($patient->full_name, 0, 6)]))
            ->assertOk()->assertJsonStructure([['id', 'code', 'full_name', 'phone', 'date_of_birth', 'age', 'gender', 'summary']]);
        $this->getJson(route('staff.patients.search', ['q' => 'a']))->assertOk()->assertExactJson([]);

        $this->get(route('staff.patients.show', $patient))->assertOk()->assertSee($patient->code);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $patient->id, 'event' => 'viewed']);

        $this->get(route('staff.patients.create'))->assertOk();
        $this->post(route('staff.patients.store'), [
            'full_name' => 'Trần Văn Mới', 'phone' => '0911000111', 'gender' => 'male', 'date_of_birth' => '1980-01-01',
            'allergies' => 'Penicillin',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $created = Patient::where('full_name', 'Trần Văn Mới')->first();
        $this->assertSame('Penicillin', $created->allergies);

        $this->get(route('staff.patients.edit', $created))->assertOk();
        $this->put(route('staff.patients.update', $created), ['full_name' => 'Trần Văn Mới Sửa', 'address' => '1 Lê Lợi'])
            ->assertRedirect(route('staff.patients.show', $created));
        $this->assertSame('1 Lê Lợi', $created->fresh()->address);
    }

    public function test_cashier_cannot_use_reception_features(): void
    {
        $this->loginAs('thungan@phongkham.test');
        $appointment = $this->bookToday();

        $this->get(route('staff.appointments.index'))->assertForbidden();
        $this->get(route('staff.appointments.create'))->assertForbidden();
        $this->get(route('staff.reception.checkin'))->assertForbidden();
        $this->get(route('staff.reception.walkin'))->assertForbidden();
        $this->get(route('staff.queue.index'))->assertForbidden();
        $this->post(route('staff.reception.checkin.store', $appointment))->assertForbidden();
        $this->post(route('staff.appointments.cancel', $appointment))->assertForbidden();
        $this->get(route('staff.patients.create'))->assertForbidden();
    }
}
