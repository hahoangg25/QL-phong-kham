<?php

namespace App\Http\Controllers\Admin\Doctor;

use App\Enums\ServiceOrderStatus;
use App\Enums\ServiceType;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Catalog\Doctor;
use App\Models\Catalog\Icd10Code;
use App\Models\Pharmacy\Medicine;
use App\Models\Catalog\Service;
use App\Models\Clinical\ServiceOrder;
use App\Models\Clinical\Visit;
use App\Services\ExaminationService;
use App\Services\QueueService;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Phân hệ bác sĩ: hàng đợi và gọi bệnh nhân (BS-03), bệnh án điện tử (BS-04..BS-12).
 * Quy tắc ghi bệnh án (BR-07, BR-08) được kiểm tra trong ExaminationService.
 */
class ExamController extends Controller
{
    public function __construct(
        protected QueueService $queue,
        protected ExaminationService $exam,
    ) {}

    /** BS-03: hàng đợi hôm nay của bác sĩ. */
    public function index(Request $request)
    {
        $doctor = $request->user()->doctor;

        if (! $doctor) {
            return view('admin.doctor.exam.index', ['doctor' => null, 'board' => null]);
        }

        $board = $this->queue->board($doctor);

        if ($request->boolean('partial')) {
            return view('admin.doctor.exam._queue', compact('doctor', 'board'));
        }

        return view('admin.doctor.exam.index', compact('doctor', 'board'));
    }

    public function callNext(Request $request)
    {
        $doctor = $this->doctor($request);
        $visit = $this->queue->callNext($doctor, $request->user());

        if (! $visit) {
            return back()->with('info', 'Không còn bệnh nhân nào đang chờ khám.');
        }

        return redirect()->route('staff.exam.show', $visit)
            ->with('success', 'Đã gọi số '.$visit->queue_number.' - '.$visit->patient->full_name.' vào khám.');
    }

    public function call(Request $request, Visit $visit)
    {
        $this->ensureOwner($request, $visit);
        $this->queue->call($visit, $request->user());

        return redirect()->route('staff.exam.show', $visit)
            ->with('success', 'Đã gọi số '.$visit->queue_number.' - '.$visit->patient->full_name.' vào khám.');
    }

    public function recall(Request $request, Visit $visit)
    {
        $this->ensureOwner($request, $visit);
        $this->queue->recall($visit);

        return back()->with('success', 'Đã gọi lại số '.$visit->queue_number.' trên màn hình sảnh.');
    }

    public function skip(Request $request, Visit $visit)
    {
        $this->ensureOwner($request, $visit);
        $this->queue->skip($visit, $request->user());

        return redirect()->route('staff.exam.index')
            ->with('info', 'Bệnh nhân số '.$visit->queue_number.' không có mặt, đã chuyển xuống cuối hàng đợi.');
    }

    /** BS-04..BS-12: màn hình khám bệnh. */
    public function show(Request $request, Visit $visit)
    {
        $doctor = $request->user()->doctor;

        // Bác sĩ khác chỉ được xem (BR-07): chuyển sang bệnh án chỉ đọc
        if (! $doctor || $doctor->id !== $visit->doctor_id) {
            abort_unless($request->user()->can('medical.view'), 403);

            return redirect()->route('staff.records.show', $visit);
        }

        $visit->load([
            'patient.user', 'appointment', 'room', 'invoice', 'checkedInBy',
            'diagnoses', 'serviceOrders.service', 'serviceOrders.result.performer', 'serviceOrders.orderedBy',
            'prescription.items.medicine', 'addenda.user',
        ]);

        $canEdit = ! $visit->isLocked()
            && in_array($visit->status, [VisitStatus::InProgress, VisitStatus::AwaitingResults], true);
        $rxEditable = $canEdit && (! $visit->prescription || $visit->prescription->isEditable());

        $services = Service::active()->paraclinical()->orderBy('type')->orderBy('name')->get()
            ->groupBy(fn (Service $s) => $s->type->value);
        $orderedServiceIds = $visit->serviceOrders
            ->where('status', '!=', ServiceOrderStatus::Cancelled)
            ->pluck('service_id')->all();

        $history = Visit::with(['doctor.user', 'specialty', 'diagnoses', 'prescription.items.medicine'])
            ->where('patient_id', $visit->patient_id)
            ->where('id', '!=', $visit->id)
            ->where('status', VisitStatus::Completed->value)
            ->latest('visit_date')->latest('id')
            ->take(10)
            ->get();

        $rxItems = $this->prescriptionRows($visit);

        Audit::viewed($visit, 'Bác sĩ mở bệnh án');

        return view('admin.doctor.exam.show', [
            'visit' => $visit,
            'patient' => $visit->patient,
            'canEdit' => $canEdit,
            'rxEditable' => $rxEditable,
            'services' => $services,
            'serviceTypes' => ServiceType::options(),
            'orderedServiceIds' => $orderedServiceIds,
            'history' => $history,
            'rxItems' => $rxItems,
            'tab' => $request->query('tab', 'clinical'),
        ]);
    }

    /** BS-05 (và kết luận, dặn dò, hẹn tái khám - BS-11). */
    public function saveClinical(Request $request, Visit $visit)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'symptoms' => ['nullable', 'string', 'max:2000'],
            'pulse' => ['nullable', 'integer', 'between:20,250'],
            'bp_systolic' => ['nullable', 'integer', 'between:50,260'],
            'bp_diastolic' => ['nullable', 'integer', 'between:30,180'],
            'temperature' => ['nullable', 'numeric', 'between:34,43'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'spo2' => ['nullable', 'integer', 'between:50,100'],
            'weight' => ['nullable', 'numeric', 'between:0.5,300'],
            'height' => ['nullable', 'numeric', 'between:30,250'],
            'clinical_notes' => ['nullable', 'string', 'max:5000'],
            'conclusion' => ['nullable', 'string', 'max:5000'],
            'advice' => ['nullable', 'string', 'max:5000'],
            'follow_up_date' => ['nullable', 'date', 'after:today'],
        ], [], [
            'pulse' => 'mạch', 'bp_systolic' => 'huyết áp tâm thu', 'bp_diastolic' => 'huyết áp tâm trương',
            'temperature' => 'nhiệt độ', 'respiratory_rate' => 'nhịp thở', 'spo2' => 'SpO2', 'weight' => 'cân nặng',
            'height' => 'chiều cao', 'clinical_notes' => 'khám lâm sàng', 'conclusion' => 'kết luận',
            'advice' => 'lời dặn', 'follow_up_date' => 'ngày tái khám',
        ]);

        $this->exam->saveClinical($visit, $data, $request->user());

        $tab = $request->input('tab') === 'conclusion' ? 'conclusion' : 'clinical';

        return redirect()->route('staff.exam.show', [$visit, 'tab' => $tab])->with('success', 'Đã lưu thông tin khám.');
    }

    /** BS-06: chẩn đoán ICD-10 (một bệnh chính, nhiều bệnh kèm theo). */
    public function saveDiagnoses(Request $request, Visit $visit)
    {
        $request->validate([
            'diagnoses' => ['nullable', 'array', 'max:10'],
            'diagnoses.*.icd10_code' => ['required', 'string', 'max:10', 'exists:icd10_codes,code'],
            'diagnoses.*.name' => ['required', 'string', 'max:255'],
            'diagnoses.*.note' => ['nullable', 'string', 'max:500'],
            'primary' => ['nullable'],
        ], [
            'diagnoses.*.icd10_code.exists' => 'Mã ICD-10 ":input" không có trong danh mục.',
            'diagnoses.*.icd10_code.required' => 'Vui lòng chọn mã ICD-10 cho từng dòng chẩn đoán.',
            'diagnoses.*.name.required' => 'Vui lòng nhập tên bệnh cho từng dòng chẩn đoán.',
        ]);

        $primaryKey = (string) $request->input('primary');
        $items = collect($request->input('diagnoses', []))
            ->map(fn ($row, $key) => $row + ['is_primary' => (string) $key === $primaryKey])
            // Bệnh chính đứng đầu để service đánh dấu đúng
            ->sortByDesc('is_primary')
            ->values()
            ->all();

        $this->exam->syncDiagnoses($visit, $items, $request->user());

        return redirect()->route('staff.exam.show', [$visit, 'tab' => 'diagnoses'])->with('success', 'Đã lưu chẩn đoán.');
    }

    /** BS-07: chỉ định cận lâm sàng. */
    public function orderServices(Request $request, Visit $visit)
    {
        $data = $request->validate([
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'service_ids.required' => 'Vui lòng chọn ít nhất một dịch vụ cận lâm sàng.',
        ]);

        $this->exam->orderServices($visit, $data['service_ids'], $data['note'] ?? null, $request->user());

        return redirect()->route('staff.exam.show', [$visit, 'tab' => 'orders'])
            ->with('success', 'Đã chỉ định '.count($data['service_ids']).' dịch vụ. Lượt khám chuyển sang "Chờ kết quả CLS".');
    }

    public function cancelOrder(Request $request, ServiceOrder $serviceOrder)
    {
        $this->exam->cancelOrder($serviceOrder, $request->user());

        return redirect()->route('staff.exam.show', [$serviceOrder->visit_id, 'tab' => 'orders'])
            ->with('success', 'Đã hủy chỉ định '.$serviceOrder->service->name.'.');
    }

    /** BS-09: kê đơn - cảnh báo tồn kho, dị ứng (BR-10). */
    public function savePrescription(Request $request, Visit $visit)
    {
        $request->validate([
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'items.*.quantity' => ['nullable', 'required_with:items.*.medicine_id', 'integer', 'between:1,1000'],
            'items.*.dosage' => ['nullable', 'required_with:items.*.medicine_id', 'string', 'max:100'],
            'items.*.usage' => ['nullable', 'string', 'max:255'],
            'items.*.days' => ['nullable', 'integer', 'between:1,365'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'items.*.quantity.required_with' => 'Vui lòng nhập số lượng cho từng thuốc.',
            'items.*.dosage.required_with' => 'Vui lòng nhập liều dùng cho từng thuốc.',
            'items.*.quantity.between' => 'Số lượng thuốc phải từ 1 đến 1000.',
        ]);

        $items = collect($request->input('items', []))->filter(fn ($i) => ! empty($i['medicine_id']))->values()->all();
        $warnings = $this->exam->savePrescription($visit, $items, $request->input('note'), $request->user());

        $redirect = redirect()->route('staff.exam.show', [$visit, 'tab' => 'prescription'])
            ->with('success', $items ? 'Đã lưu đơn thuốc ('.count($items).' thuốc).' : 'Đã xóa đơn thuốc.');

        return $warnings ? $redirect->with('warning', $warnings) : $redirect;
    }

    /** BS-12: hoàn thành lượt khám, khóa bệnh án, lập hóa đơn, hẹn tái khám. */
    public function complete(Request $request, Visit $visit)
    {
        $this->exam->complete($visit, $request->user());
        $visit->refresh()->load(['invoice', 'patient']);

        $message = 'Đã hoàn thành lượt khám '.$visit->code.' của '.$visit->patient->full_name.'.';
        if ($visit->invoice) {
            $message .= ' Hóa đơn '.$visit->invoice->code.' ('.money($visit->invoice->total).') đã chuyển tới thu ngân.';
        }

        if ($visit->follow_up_date) {
            $followUp = Appointment::where('patient_id', $visit->patient_id)
                ->where('doctor_id', $visit->doctor_id)
                ->where('source', 'follow_up')
                ->whereDate('appointment_date', $visit->follow_up_date)
                ->active()
                ->latest('id')
                ->first();

            $message .= $followUp
                ? ' Đã đặt lịch tái khám lúc '.$followUp->scheduled_at->format('H:i d/m/Y').' (mã '.$followUp->code.').'
                : ' Chưa tìm được khung giờ trống ngày '.$visit->follow_up_date->format('d/m/Y').' để đặt lịch tái khám, vui lòng hướng dẫn bệnh nhân đặt lịch.';
        }

        return redirect()->route('staff.exam.index')->with('success', $message);
    }

    /** BR-08: ghi bổ sung vào bệnh án đã khóa. */
    public function addendum(Request $request, Visit $visit)
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:5000']]);
        $this->exam->addAddendum($visit, $data['content'], $request->user());

        return back()->with('success', 'Đã ghi bổ sung vào bệnh án.');
    }

    /** Tra cứu ICD-10 cho ô chẩn đoán (JSON). */
    public function searchIcd(Request $request)
    {
        $q = trim((string) $request->query('q'));

        if ($q === '') {
            return response()->json([]);
        }

        return response()->json(
            Icd10Code::search($q)->orderBy('code')->limit(15)->get(['code', 'name'])
        );
    }

    /** Tra cứu thuốc kèm tồn kho còn hạn cho ô kê đơn (JSON). */
    public function searchMedicine(Request $request)
    {
        $q = trim((string) $request->query('q'));

        if ($q === '') {
            return response()->json([]);
        }

        $items = Medicine::active()->withUsableStock()->search($q)->orderBy('name')->limit(15)->get()
            ->map(fn (Medicine $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'full_name' => $m->fullName(),
                'active_ingredient' => $m->active_ingredient,
                'unit' => $m->unit,
                'price' => $m->price,
                'stock' => (int) $m->usable_stock,
            ]);

        return response()->json($items);
    }

    // ------------------------------------------------------------------

    protected function doctor(Request $request): Doctor
    {
        $doctor = $request->user()->doctor;
        abort_unless($doctor, 403, 'Tài khoản chưa có hồ sơ bác sĩ.');

        return $doctor;
    }

    /** Chỉ bác sĩ phụ trách lượt khám được gọi/bỏ qua bệnh nhân. */
    protected function ensureOwner(Request $request, Visit $visit): void
    {
        $doctor = $this->doctor($request);
        abort_unless($doctor->id === $visit->doctor_id, 403, 'Bệnh nhân không thuộc hàng đợi của bác sĩ.');
    }

    /** Dữ liệu dòng đơn thuốc hiện có (kèm tồn kho) cho trình soạn đơn. */
    protected function prescriptionRows(Visit $visit): array
    {
        $items = $visit->prescription?->items ?? collect();
        if ($items->isEmpty()) {
            return [];
        }

        $stocks = Medicine::withUsableStock()->whereIn('id', $items->pluck('medicine_id'))->get()->keyBy('id');

        return $items->map(fn ($item) => [
            'medicine_id' => $item->medicine_id,
            'name' => $item->medicine->fullName(),
            'active_ingredient' => $item->medicine->active_ingredient,
            'unit' => $item->medicine->unit,
            'price' => $item->unit_price,
            'stock' => (int) ($stocks[$item->medicine_id]->usable_stock ?? 0),
            'quantity' => $item->quantity,
            'dosage' => $item->dosage,
            'usage' => $item->usage,
            'days' => $item->days,
        ])->values()->all();
    }
}
