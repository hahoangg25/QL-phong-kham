<?php

namespace App\Http\Controllers\Client\Account;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Clinical\Visit;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $upcoming = Appointment::with(['patient', 'doctor.user', 'specialty'])
            ->ownedBy($user)
            ->upcoming()
            ->take(3)
            ->get();

        $stats = [
            'profiles' => $user->patients()->count(),
            'upcoming' => Appointment::ownedBy($user)->upcoming()->count(),
            'visits' => Visit::ownedBy($user)->where('status', VisitStatus::Completed->value)->count(),
        ];

        $notifications = $user->unreadNotifications()->latest()->take(5)->get();

        return view('client.account.dashboard', [
            'user' => $user,
            'upcoming' => $upcoming,
            'stats' => $stats,
            'notifications' => $notifications,
            'noShowThreshold' => (int) setting('no_show_threshold'),
            'noShowPolicy' => setting('no_show_policy'),
        ]);
    }
}
