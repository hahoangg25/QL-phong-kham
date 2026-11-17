<?php

namespace App\Http\Controllers\Client\Account;

use App\Enums\VisitStatus;
use App\Http\Controllers\Client\Account\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Clinical\LabResult;
use App\Models\Clinical\Review;
use App\Models\Clinical\Visit;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** BN-15 lịch sử khám (chẩn đoán, đơn thuốc, kết quả CLS), BN-19 đánh giá bác sĩ. */
class VisitController extends Controller
{
    use AuthorizesOwnership;

    public function index(Request $request)
    {
        $user = $request->user();
        $patientId = $request->integer('patient_id') ?: null;

        $visits = Visit::with(['patient', 'doctor.user', 'specialty', 'diagnoses', 'prescription', 'review'])
            ->ownedBy($user)
            ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('client.account.visits.index', [
            'visits' => $visits,
            'patients' => $user->patients()->orderBy('id')->get(),
            'patientId' => $patientId,
        ]);
    }

    public function show(Visit $visit)
    {
        $this->authorizeOwner($visit);

        $visit->load([
            'patient', 'doctor.user', 'doctor.room', 'specialty', 'appointment', 'room',
            'diagnoses', 'serviceOrders.service', 'serviceOrders.result', 'prescription.items.medicine',
            'invoice', 'addenda.user', 'review',
        ]);

        Audit::viewed($visit, 'Bệnh nhân xem kết quả khám');

        return view('client.account.visits.show', [
            'visit' => $visit,
            'canReview' => $visit->status === VisitStatus::Completed && ! $visit->review,
        ]);
    }

    public function review(Request $request, Visit $visit)
    {
        $this->authorizeOwner($visit);

        if ($visit->status !== VisitStatus::Completed) {
            return back()->with('error', 'Chỉ đánh giá được lượt khám đã hoàn thành.');
        }
        if ($visit->review()->exists()) {
            return back()->with('error', 'Bạn đã đánh giá lượt khám này.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        Review::create([
            'visit_id' => $visit->id,
            'doctor_id' => $visit->doctor_id,
            'patient_id' => $visit->patient_id,
            'user_id' => $request->user()->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        return back()->with('success', 'Cảm ơn bạn đã đánh giá bác sĩ!');
    }

    /** Tải/xem tệp kết quả cận lâm sàng (chỉ chủ hồ sơ). */
    public function attachment(LabResult $labResult)
    {
        $labResult->load('serviceOrder.visit.patient');
        $visit = $labResult->serviceOrder?->visit;
        abort_unless($visit, 404);
        $this->authorizeOwner($visit);

        $disk = Storage::disk('local');
        abort_unless($labResult->attachment_path && $disk->exists($labResult->attachment_path), 404);

        Audit::viewed($visit, 'Bệnh nhân xem tệp kết quả CLS', ['lab_result_id' => $labResult->id]);

        return $disk->response($labResult->attachment_path, $labResult->attachment_name ?: basename($labResult->attachment_path));
    }
}
