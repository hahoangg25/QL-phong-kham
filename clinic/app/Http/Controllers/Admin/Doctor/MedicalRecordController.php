<?php

namespace App\Http\Controllers\Admin\Doctor;

use App\Enums\ServiceOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Clinical\LabResult;
use App\Models\Clinical\Visit;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Xem bệnh án chỉ đọc (BS-04), in đơn thuốc/phiếu chỉ định (BS-13), tải tệp kết quả CLS. */
class MedicalRecordController extends Controller
{
    public function show(Visit $visit)
    {
        $visit->load($this->relations());

        $history = Visit::with(['doctor.user', 'diagnoses'])
            ->where('patient_id', $visit->patient_id)
            ->where('id', '!=', $visit->id)
            ->latest('visit_date')->latest('id')
            ->take(10)->get();

        Audit::viewed($visit, 'Xem bệnh án (chỉ đọc)');

        return view('admin.doctor.records.show', compact('visit', 'history'));
    }

    public function printPrescription(Visit $visit)
    {
        $visit->load($this->relations());
        abort_unless($visit->prescription && $visit->prescription->items->isNotEmpty(), 404, 'Lượt khám chưa có đơn thuốc.');

        Audit::viewed($visit, 'In đơn thuốc');

        return view('admin.doctor.records.print-prescription', compact('visit'));
    }

    public function printOrders(Visit $visit)
    {
        $visit->load($this->relations());
        $orders = $visit->serviceOrders->where('status', '!=', ServiceOrderStatus::Cancelled)->values();
        if ($orders->isEmpty()) {
            return back()->with('warning', 'Lượt khám chưa có chỉ định cận lâm sàng để in.');
        }

        Audit::viewed($visit, 'In phiếu chỉ định cận lâm sàng');

        return view('admin.doctor.records.print-orders', compact('visit', 'orders'));
    }

    public function attachment(Request $request, LabResult $labResult)
    {
        abort_unless($request->user()->can('medical.view') || $request->user()->can('lab.manage'), 403);
        abort_unless($labResult->attachment_path && Storage::disk('local')->exists($labResult->attachment_path), 404);

        Audit::viewed($labResult->serviceOrder->visit, 'Tải tệp kết quả cận lâm sàng', ['lab_result_id' => $labResult->id]);

        return Storage::disk('local')->response(
            $labResult->attachment_path,
            $labResult->attachment_name ?: basename($labResult->attachment_path)
        );
    }

    protected function relations(): array
    {
        return [
            'patient', 'doctor.user', 'specialty', 'room', 'appointment', 'invoice',
            'diagnoses', 'serviceOrders.service', 'serviceOrders.result.performer', 'serviceOrders.orderedBy',
            'prescription.items.medicine', 'prescription.dispenser', 'addenda.user',
        ];
    }
}
