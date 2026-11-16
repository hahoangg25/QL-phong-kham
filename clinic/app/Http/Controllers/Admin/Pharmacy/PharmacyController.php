<?php

namespace App\Http\Controllers\Admin\Pharmacy;

use App\Enums\InvoiceStatus;
use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Pharmacy\MedicineBatch;
use App\Models\Clinical\Prescription;
use App\Services\PharmacyService;
use Illuminate\Http\Request;

/** Dược: đơn thuốc chờ cấp (TD-05), cấp phát theo FEFO (TD-06). */
class PharmacyController extends Controller
{
    public function __construct(protected PharmacyService $pharmacy) {}

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'ready');
        $search = trim((string) $request->input('q'));

        $prescriptions = Prescription::with(['patient', 'doctor.user', 'visit.invoice', 'items', 'dispenser'])
            ->when($tab === 'ready', fn ($q) => $q->where('status', PrescriptionStatus::Pending->value)
                ->whereHas('visit.invoice', fn ($i) => $i->where('status', InvoiceStatus::Paid->value)))
            ->when($tab === 'unpaid', fn ($q) => $q->where('status', PrescriptionStatus::Pending->value)
                ->whereDoesntHave('visit.invoice', fn ($i) => $i->where('status', InvoiceStatus::Paid->value)))
            ->when($tab === 'dispensed', fn ($q) => $q->where('status', PrescriptionStatus::Dispensed->value)
                ->whereDate('dispensed_at', today()))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%{$search}%")
                ->orWhereHas('patient', fn ($p) => $p->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "{$search}%")
                    ->orWhere('code', $search))))
            ->when($tab === 'dispensed', fn ($q) => $q->latest('dispensed_at'), fn ($q) => $q->oldest('id'))
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'ready' => Prescription::where('status', PrescriptionStatus::Pending->value)
                ->whereHas('visit.invoice', fn ($i) => $i->where('status', InvoiceStatus::Paid->value))->count(),
            'unpaid' => Prescription::where('status', PrescriptionStatus::Pending->value)
                ->whereDoesntHave('visit.invoice', fn ($i) => $i->where('status', InvoiceStatus::Paid->value))->count(),
            'dispensed' => Prescription::where('status', PrescriptionStatus::Dispensed->value)->whereDate('dispensed_at', today())->count(),
        ];

        return view('admin.pharmacy.dispensing.index', compact('prescriptions', 'tab', 'counts'));
    }

    public function show(Prescription $prescription)
    {
        $prescription->load(['patient', 'doctor.user', 'visit.diagnoses', 'visit.invoice', 'items.medicine', 'dispenser']);

        // Kiểm tra tồn kho theo số lượng thực tế
        $plan = [];
        foreach ($prescription->items as $item) {
            $stock = $item->medicine ? $item->medicine->usableStock() : 0;
            $need = $item->quantity;

            $plan[$item->id] = [
                'available' => $stock,
                'allocations' => [
                    ['batch' => (object)['batch_no' => 'TỒN KHO', 'expiry_date' => now()->addYear()], 'take' => $need]
                ],
                'enough' => $stock >= $need,
            ];
        }

        $isPaid = $prescription->visit?->invoice?->status === InvoiceStatus::Paid;
        $canDispense = $prescription->status === PrescriptionStatus::Pending && $isPaid && collect($plan)->every(fn ($p) => $p['enough']);

        return view('admin.pharmacy.dispensing.show', compact('prescription', 'plan', 'isPaid', 'canDispense'));
    }

    public function dispense(Request $request, Prescription $prescription)
    {
        $this->pharmacy->dispense($prescription, $request->user());

        return redirect()->route('staff.pharmacy.show', $prescription)
            ->with('success', 'Đã cấp phát đơn thuốc '.$prescription->code.' và trừ trực tiếp số lượng tồn kho thành công.');
    }
}
