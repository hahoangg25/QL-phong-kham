<?php

namespace Tests\Feature;

use App\Enums\VisitStatus;
use App\Models\Catalog\Doctor;
use App\Models\Clinical\Patient;
use App\Models\Clinical\Review;
use App\Models\Catalog\Specialty;
use App\Models\Clinical\Visit;
use Tests\TestCase;

class SiteTest extends TestCase
{
    public function test_home_page_shows_real_data(): void
    {
        $doctor = Doctor::with('user')->orderByDesc('rating_avg')->first();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Chuyên khoa nổi bật')
            ->assertSee('Quy trình đặt lịch 4 bước')
            ->assertSee($doctor->user->name)
            ->assertSee(config('clinic.hotline'));
    }

    public function test_specialty_pages(): void
    {
        $specialty = Specialty::where('slug', 'tim-mach')->first();

        $this->get(route('specialties.index'))->assertOk()->assertSee('Nội tổng quát')->assertSee('Tim mạch');

        $this->get(route('specialties.show', $specialty))
            ->assertOk()
            ->assertSee('Giới thiệu chuyên khoa Tim mạch')
            ->assertSee('Võ Đức Thịnh')
            ->assertSee('Siêu âm tim Doppler màu');

        $specialty->update(['is_active' => false]);
        $this->get(route('specialties.show', $specialty))->assertNotFound();
    }

    public function test_doctor_list_filters(): void
    {
        $derma = Specialty::where('slug', 'da-lieu')->first();

        $this->get(route('doctors.index'))->assertOk()->assertSee('Trần Minh Quang')->assertSee('Đặng Ngọc Mai');

        $this->get(route('doctors.index', ['specialty_id' => $derma->id]))
            ->assertOk()->assertSee('Đặng Ngọc Mai')->assertDontSee('Trần Minh Quang');

        $this->get(route('doctors.index', ['q' => 'Quang']))
            ->assertOk()->assertSee('Trần Minh Quang')->assertDontSee('Đặng Ngọc Mai');
    }

    public function test_doctor_profile_shows_schedule_availability_and_reviews(): void
    {
        $doctor = Doctor::with('user')->first();
        $patient = Patient::where('full_name', 'Nguyễn Văn An')->first();
        $visit = Visit::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'specialty_id' => $doctor->specialty_id,
            'visit_date' => today()->subDay(), 'queue_number' => 1, 'queue_sort' => now()->subDay(),
            'status' => VisitStatus::Completed,
        ]);
        Review::create([
            'visit_id' => $visit->id, 'doctor_id' => $doctor->id, 'patient_id' => $patient->id,
            'user_id' => $patient->user_id, 'rating' => 5, 'comment' => 'Bác sĩ rất tận tâm',
        ]);

        $this->get(route('doctors.show', $doctor))
            ->assertOk()
            ->assertSee($doctor->user->name)
            ->assertSee('Lịch làm việc hàng tuần')
            ->assertSee('Lịch trống 7 ngày tới')
            ->assertSee(money($doctor->consultation_fee))
            ->assertSee('Bác sĩ rất tận tâm')
            ->assertSee(mask_name('Nguyễn Văn An'))
            ->assertDontSee('Nguyễn Văn An');

        $doctor->update(['is_active' => false]);
        $this->get(route('doctors.show', $doctor))->assertNotFound();
    }

    public function test_service_price_list(): void
    {
        $this->get(route('services.index'))
            ->assertOk()->assertSee('Xét nghiệm')->assertSee('Chẩn đoán hình ảnh')->assertSee('Khám nội tổng quát');

        $this->get(route('services.index', ['type' => 'lab']))
            ->assertOk()->assertSee('Định lượng HbA1c')->assertDontSee('Khám nội tổng quát');

        $this->get(route('services.index', ['q' => 'Siêu âm']))
            ->assertOk()->assertSee('Siêu âm thai')->assertDontSee('Định lượng HbA1c');
    }

    public function test_terms_page(): void
    {
        $this->get(route('terms'))->assertOk()->assertSee('Luật Bảo vệ dữ liệu cá nhân 2025');
    }

    public function test_lobby_screen_and_data(): void
    {
        $this->get(route('lobby'))->assertOk()->assertSee('Màn hình hàng đợi');

        $this->getJson(route('lobby.data'))->assertOk()->assertJsonStructure(['time', 'date', 'rooms'])->assertJsonCount(0, 'rooms');

        $doctor = Doctor::with('room')->first();
        $patients = Patient::whereNotNull('user_id')->take(3)->get();
        foreach ($patients as $i => $p) {
            Visit::create([
                'patient_id' => $p->id, 'doctor_id' => $doctor->id, 'specialty_id' => $doctor->specialty_id,
                'room_id' => $doctor->room_id, 'visit_date' => today(), 'queue_number' => $i + 1,
                'queue_sort' => now()->addMinutes($i), 'called_at' => $i === 0 ? now() : null,
                'status' => $i === 0 ? VisitStatus::InProgress : VisitStatus::Waiting,
            ]);
        }

        $response = $this->getJson(route('lobby.data'))
            ->assertOk()
            ->assertJsonStructure(['rooms' => [['doctor_id', 'doctor', 'room', 'current' => ['number', 'name'], 'waiting', 'waiting_count']]])
            ->assertJsonPath('rooms.0.room', $doctor->room->code)
            ->assertJsonPath('rooms.0.current.number', 1)
            ->assertJsonPath('rooms.0.waiting_count', 2);

        // Màn hình công khai không lộ họ tên đầy đủ
        $this->assertStringNotContainsString($patients[0]->full_name, $response->getContent());
    }
}
