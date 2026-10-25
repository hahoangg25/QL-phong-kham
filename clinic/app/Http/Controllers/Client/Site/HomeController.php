<?php

namespace App\Http\Controllers\Client\Site;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Doctor;
use App\Models\Clinical\Review;
use App\Models\Catalog\Specialty;
use App\Models\Scheduling\TimeSlot;
use App\Models\Clinical\Visit;

/** Trang chủ cổng thông tin. */
class HomeController extends Controller
{
    public function index()
    {
        $stats = [
            'doctors' => Doctor::active()->count(),
            'visits' => Visit::where('status', VisitStatus::Completed->value)->count(),
            'specialties' => Specialty::where('is_active', true)->count(),
            'rating' => round((float) Review::where('is_hidden', false)->avg('rating'), 1),
            'reviews' => Review::where('is_hidden', false)->count(),
        ];

        $activeDoctors = fn ($q) => $q->where('is_active', true);

        $specialties = Specialty::active()->withCount(['doctors' => $activeDoctors])->take(6)->get();
        $specialtyOptions = Specialty::active()->get(['id', 'name']);

        $doctors = Doctor::active()
            ->with(['user', 'specialty'])
            ->orderByDesc('rating_avg')
            ->orderByDesc('rating_count')
            ->take(6)
            ->get();

        // Khung giờ trống gần nhất còn đặt online được (thẻ nổi ở hero)
        $nextSlot = TimeSlot::with('doctor.user')
            ->where('is_active', true)
            ->whereHas('doctor', $activeDoctors)
            ->where('starts_at', '>=', now()->addHours((int) setting('booking_min_hours_before')))
            ->whereColumn('booked_count', '<', 'capacity')
            ->orderBy('starts_at')
            ->first();

        return view('client.site.home', compact('stats', 'specialties', 'specialtyOptions', 'doctors', 'nextSlot'));
    }
}
