<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\ServiceOrderStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Catalog\Doctor;
use App\Models\Billing\Invoice;
use App\Models\Billing\Payment;
use App\Models\Clinical\Prescription;
use App\Models\Clinical\ServiceOrder;
use App\Models\Clinical\Visit;
use App\Services\PharmacyService;
use App\Services\QueueService;
use Illuminate\Http\Request;

/** Trang tổng quan hệ thống nội bộ - nội dung thay đổi theo quyền của người dùng. */
class DashboardController extends Controller
{
    public function index(Request $request, QueueService $queue, PharmacyService $pharmacy)
    {
        $user = $request->user();
        $data = [];

        // Quản trị: chỉ số nhanh trong ngày (QT-15)
        if ($user->can('reports.view')) {
            $from = now()->subDays(30);
            $finished = Appointment::whereBetween('scheduled_at', [$from, now()])
                ->whereIn('status', [
                    AppointmentStatus::Completed->value, AppointmentStatus::NoShow->value, AppointmentStatus::CheckedIn->value,
                    AppointmentStatus::InProgress->value, AppointmentStatus::AwaitingResults->value,
                ])
                ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
            $totalFinished = (int) $finished->sum();

            $payments = Payment::where('status', PaymentStatus::Success->value)
                ->whereDate('paid_at', today())
                ->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');

            $data['kpi'] = [
                'visits_today' => Visit::whereDate('visit_date', today())->where('status', '!=', VisitStatus::Cancelled->value)->count(),
                'revenue_today' => (int) ($payments['payment'] ?? 0) + (int) ($payments['deposit'] ?? 0) - (int) ($payments['refund'] ?? 0),
                'pending_appointments' => Appointment::where('status', AppointmentStatus::Pending->value)->where('scheduled_at', '>=', now())->count(),
                'no_show_rate' => $totalFinished ? round(($finished[AppointmentStatus::NoShow->value] ?? 0) * 100 / $totalFinished, 1) : 0,
            ];
        }

        // Lễ tân: lịch hẹn hôm nay, chờ xác nhận, hàng đợi theo bác sĩ
        if ($user->can('appointments.view') || $user->can('reception.manage')) {
            $data['todayStatus'] = Appointment::whereDate('appointment_date', today())
                ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

            $data['pendingList'] = Appointment::with(['patient', 'doctor.user', 'specialty'])
                ->where('status', AppointmentStatus::Pending->value)
                ->where('scheduled_at', '>=', now())
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get();

            $counts = Visit::today()->selectRaw('doctor_id, status, COUNT(*) as c')
                ->groupBy('doctor_id', 'status')->get()->groupBy('doctor_id');
            $data['doctorQueues'] = Doctor::with(['user', 'room', 'specialty'])
                ->whereIn('id', $counts->keys())->get()
                ->map(fn (Doctor $d) => [
                    'doctor' => $d,
                    'waiting' => (int) $counts[$d->id]->where('status', VisitStatus::Waiting)->sum('c'),
                    'in_progress' => (int) $counts[$d->id]->whereIn('status', [VisitStatus::InProgress, VisitStatus::AwaitingResults])->sum('c'),
                    'done' => (int) $counts[$d->id]->where('status', VisitStatus::Completed)->sum('c'),
                ])
                ->sortByDesc('waiting')->values();
        }

        // Bác sĩ: hàng đợi của tôi hôm nay
        if ($user->can('medical.examine') && $user->doctor) {
            $data['myBoard'] = $queue->board($user->doctor);
        }

        // Cận lâm sàng
        if ($user->can('lab.manage')) {
            $data['labPending'] = ServiceOrder::where('status', ServiceOrderStatus::Pending->value)->count();
            $data['labInProgress'] = ServiceOrder::where('status', ServiceOrderStatus::InProgress->value)->count();
        }

        // Thu ngân
        if ($user->can('billing.manage')) {
            $unpaid = Invoice::where('status', InvoiceStatus::Unpaid->value);
            $data['unpaidCount'] = (clone $unpaid)->count();
            $data['unpaidTotal'] = (int) (clone $unpaid)->get(['total', 'paid_amount'])->sum(fn ($i) => max(0, $i->total - $i->paid_amount));
        }

        // Dược & kho
        if ($user->can('pharmacy.dispense') || $user->can('inventory.manage')) {
            $data['toDispense'] = Prescription::where('status', PrescriptionStatus::Pending->value)
                ->whereHas('visit.invoice', fn ($q) => $q->where('status', InvoiceStatus::Paid->value))
                ->count();
            $alerts = $pharmacy->alerts();
            $data['alerts'] = [
                'low' => $alerts['low']->count(),
                'nearExpiry' => $alerts['nearExpiry']->count(),
                'expired' => $alerts['expired']->count(),
            ];
        }

        return view('admin.dashboard', $data + ['user' => $user]);
    }
}
