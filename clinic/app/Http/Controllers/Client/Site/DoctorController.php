<?php

namespace App\Http\Controllers\Client\Site;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Doctor;
use App\Models\Catalog\Specialty;
use App\Models\Scheduling\WorkShift;
use App\Services\BookingService;
use Illuminate\Http\Request;

/** BN-06: tra cứu bác sĩ (hồ sơ, chuyên môn, phí khám, lịch làm việc, đánh giá). */
class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $specialtyId = $request->integer('specialty_id') ?: null;

        $doctors = Doctor::active()
            ->with(['user', 'specialty'])
            ->when($q !== '', fn ($query) => $query->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")))
            ->when($specialtyId, fn ($query) => $query->where('specialty_id', $specialtyId))
            ->orderByDesc('rating_avg')
            ->orderByDesc('experience_years')
            ->paginate(12)
            ->withQueryString();

        $specialties = Specialty::active()->get(['id', 'name']);

        return view('client.site.doctors.index', compact('doctors', 'specialties', 'q', 'specialtyId'));
    }

    public function show(Doctor $doctor, BookingService $booking)
    {
        abort_unless($doctor->is_active, 404);

        $doctor->load(['user', 'specialty', 'room', 'workShifts' => fn ($q) => $q->where('is_active', true)->with('room')->orderBy('start_time')]);

        // Lịch làm việc tuần, thứ tự Thứ 2 -> Chủ nhật
        $shiftsByDay = $doctor->workShifts->groupBy('weekday');
        $schedule = collect(WorkShift::WEEKDAYS)->map(fn ($label, $weekday) => [
            'label' => $label,
            'shifts' => $shiftsByDay->get($weekday, collect()),
        ]);

        // Số khung giờ còn đặt được trong 7 ngày tới
        $availability = collect(range(0, 6))->map(function (int $i) use ($doctor, $booking) {
            $date = today()->addDays($i);
            $slots = collect($booking->availableSlots($doctor->id, $date));

            return [
                'date' => $date,
                'total' => $slots->count(),
                'bookable' => $slots->where('bookable', true)->count(),
            ];
        });

        $reviews = $doctor->reviews()
            ->where('is_hidden', false)
            ->with('patient')
            ->latest()
            ->take(10)
            ->get();

        $related = Doctor::active()
            ->with(['user', 'specialty'])
            ->where('specialty_id', $doctor->specialty_id)
            ->whereKeyNot($doctor->id)
            ->take(3)
            ->get();

        return view('client.site.doctors.show', compact('doctor', 'schedule', 'availability', 'reviews', 'related'));
    }
}
