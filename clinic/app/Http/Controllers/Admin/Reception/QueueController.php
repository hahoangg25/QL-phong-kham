<?php

namespace App\Http\Controllers\Admin\Reception;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Doctor;
use App\Models\Scheduling\TimeSlot;
use App\Models\Clinical\Visit;
use App\Services\QueueService;
use Illuminate\Http\Request;

/** Điều phối hàng đợi (LT-08), dữ liệu làm mới tự động (HT-06), in phiếu số thứ tự (LT-07). */
class QueueController extends Controller
{
    public function __construct(protected QueueService $queue) {}

    public function index(Request $request)
    {
        return view('admin.reception.queue.index', $this->boardData($request) + [
            'allDoctors' => Doctor::with(['user', 'specialty'])->active()->get()->sortBy('name'),
        ]);
    }

    /** JSON cho polling/realtime: HTML của bảng hàng đợi hiện tại. */
    public function data(Request $request)
    {
        return response()->json([
            'html' => view('admin.reception.queue._board', $this->boardData($request))->render(),
            'updated_at' => now()->format('H:i:s'),
        ]);
    }

    public function moveToEnd(Visit $visit)
    {
        $this->queue->moveToEnd($visit);

        return back()->with('success', 'Đã chuyển STT '.$visit->queue_number.' xuống cuối hàng đợi.');
    }

    public function togglePriority(Visit $visit)
    {
        $this->queue->togglePriority($visit);

        return back()->with('success', ($visit->is_priority ? 'Đã ưu tiên' : 'Đã bỏ ưu tiên').' STT '.$visit->queue_number.'.');
    }

    public function transfer(Request $request, Visit $visit)
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
        ], ['doctor_id.required' => 'Vui lòng chọn bác sĩ nhận.']);

        $to = Doctor::with('user')->findOrFail($data['doctor_id']);
        $this->queue->transfer($visit, $to, $request->user());

        return back()->with('success', 'Đã chuyển bệnh nhân sang '.$to->display_name.' - STT mới '.$visit->queue_number.'.')
            ->with('ticket_url', route('staff.queue.ticket', $visit));
    }

    public function cancel(Request $request, Visit $visit)
    {
        $this->queue->cancel($visit, $request->user());

        return back()->with('success', 'Đã hủy lượt chờ STT '.$visit->queue_number.'.');
    }

    public function ticket(Visit $visit)
    {
        $visit->load(['patient', 'doctor.user', 'doctor.room', 'room', 'specialty', 'appointment']);

        return view('admin.reception.queue.ticket', [
            'visit' => $visit,
            'ahead' => $this->queue->peopleAhead($visit),
        ]);
    }

    /** Dữ liệu cho bảng: tổng quan các bác sĩ hôm nay, hoặc hàng đợi chi tiết của một bác sĩ. */
    protected function boardData(Request $request): array
    {
        $doctor = $request->filled('doctor_id')
            ? Doctor::with(['user', 'room', 'specialty'])->find($request->integer('doctor_id'))
            : null;

        if ($doctor) {
            return [
                'doctor' => $doctor,
                'board' => $this->queue->board($doctor),
                'overview' => null,
                'transferTargets' => Doctor::with(['user', 'specialty'])->active()
                    ->where('id', '!=', $doctor->id)->get()->sortBy('name'),
                'lateMinutes' => (int) setting('late_checkin_minutes'),
            ];
        }

        $counts = Visit::today()->selectRaw('doctor_id, status, COUNT(*) as c')
            ->groupBy('doctor_id', 'status')->get()->groupBy('doctor_id');
        $workingIds = TimeSlot::whereDate('date', today())->distinct()->pluck('doctor_id');
        $ids = $workingIds->merge($counts->keys())->unique();

        $overview = Doctor::with(['user', 'room', 'specialty'])->whereIn('id', $ids)->get()
            ->map(function (Doctor $d) use ($counts) {
                $rows = $counts->get($d->id, collect());
                $current = Visit::with('patient')->where('doctor_id', $d->id)->today()
                    ->where('status', VisitStatus::InProgress->value)->latest('called_at')->first();

                return [
                    'doctor' => $d,
                    'waiting' => (int) $rows->where('status', VisitStatus::Waiting)->sum('c'),
                    'in_progress' => (int) $rows->where('status', VisitStatus::InProgress)->sum('c'),
                    'awaiting' => (int) $rows->where('status', VisitStatus::AwaitingResults)->sum('c'),
                    'done' => (int) $rows->where('status', VisitStatus::Completed)->sum('c'),
                    'current' => $current,
                ];
            })
            ->sortBy(fn ($r) => $r['doctor']->room?->code ?? 'ZZZ')
            ->values();

        return ['doctor' => null, 'board' => null, 'overview' => $overview, 'transferTargets' => collect(), 'lateMinutes' => (int) setting('late_checkin_minutes')];
    }
}
