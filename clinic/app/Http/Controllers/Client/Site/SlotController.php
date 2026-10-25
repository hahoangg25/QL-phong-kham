<?php

namespace App\Http\Controllers\Client\Site;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Doctor;
use App\Models\Scheduling\TimeSlot;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BN-07: tra cứu khung giờ trống (JSON).
 *  (a) ?doctor_id&date            -> slot của một bác sĩ trong ngày
 *  (b) ?specialty_id&date         -> slot của mọi bác sĩ trong chuyên khoa ("Bất kỳ bác sĩ nào")
 *  (c) ?doctor_id|specialty_id&from&days -> số slot còn đặt được theo từng ngày (dải chọn ngày)
 * Thêm ?staff=1 (chỉ nhân viên) để bỏ giới hạn BR-02 khi lễ tân đặt hộ.
 */
class SlotController extends Controller
{
    protected const DOW = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

    public function __construct(protected BookingService $booking) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'doctor_id' => ['nullable', 'integer'],
            'specialty_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $staff = $request->boolean('staff') && (bool) $request->user()?->isStaff();

        if ($request->filled('from') || $request->filled('days')) {
            return response()->json(['days' => $this->days($request, $staff)]);
        }

        $date = Carbon::parse($request->input('date', today()->toDateString()))->startOfDay();

        if ($request->filled('doctor_id')) {
            return response()->json([
                'date' => $date->toDateString(),
                'slots' => $this->booking->availableSlots((int) $request->input('doctor_id'), $date, $staff),
            ]);
        }

        if ($request->filled('specialty_id')) {
            $doctors = Doctor::with('user')->active()
                ->where('specialty_id', $request->integer('specialty_id'))
                ->get()
                ->map(fn (Doctor $d) => [
                    'id' => $d->id,
                    'name' => $d->display_name,
                    'avatar' => $d->avatarUrl(),
                    'fee' => $d->consultation_fee,
                    'rating' => $d->rating_avg,
                    'slots' => $this->booking->availableSlots($d->id, $date, $staff),
                ])
                ->filter(fn ($d) => count($d['slots']) > 0)
                ->values();

            return response()->json(['date' => $date->toDateString(), 'doctors' => $doctors]);
        }

        return response()->json(['message' => 'Vui lòng chọn bác sĩ hoặc chuyên khoa.'], 422);
    }

    /** Số slot còn đặt được theo từng ngày để hiển thị dải chọn ngày. */
    protected function days(Request $request, bool $staff): array
    {
        $from = Carbon::parse($request->input('from', today()->toDateString()))->startOfDay();
        if ($from->lt(today())) {
            $from = today();
        }
        $days = (int) $request->input('days', 14);
        $to = $from->copy()->addDays($days - 1);

        if (! $staff) {
            $maxDate = today()->addDays((int) setting('booking_max_days_ahead'));
            if ($to->gt($maxDate)) {
                $to = $maxDate;
            }
        }

        $doctorIds = $request->filled('doctor_id')
            ? [(int) $request->input('doctor_id')]
            : Doctor::active()->where('specialty_id', $request->integer('specialty_id'))->pluck('id')->all();

        $minStart = $staff ? now() : now()->addHours((int) setting('booking_min_hours_before'));

        $slots = TimeSlot::query()
            ->whereIn('doctor_id', $doctorIds)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->where('is_active', true)
            ->where('starts_at', '>=', $minStart)
            ->withCount(['holds as active_holds' => fn ($q) => $q->where('expires_at', '>', now())])
            ->get()
            ->filter(fn (TimeSlot $s) => $s->availableCount($s->active_holds) > 0)
            ->groupBy(fn (TimeSlot $s) => $s->date->toDateString());

        $result = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $result[] = [
                'date' => $key,
                'dow' => self::DOW[$d->dayOfWeek],
                'day' => $d->format('d/m'),
                'available' => $slots->get($key, collect())->count(),
            ];
        }

        return $result;
    }
}
