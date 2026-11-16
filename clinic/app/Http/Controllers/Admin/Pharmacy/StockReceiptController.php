<?php

namespace App\Http\Controllers\Admin\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy\Medicine;
use App\Models\Pharmacy\StockReceipt;
use App\Services\PharmacyService;
use Illuminate\Http\Request;

/** TD-08: phiếu nhập kho theo lô. */
class StockReceiptController extends Controller
{
    public function __construct(protected PharmacyService $pharmacy) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));

        $receipts = StockReceipt::with('creator')
            ->withCount('batches')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%{$search}%")
                ->orWhere('supplier', 'like', "%{$search}%")))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('received_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('received_date', '<=', $request->input('to')))
            ->latest('received_date')->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pharmacy.receipts.index', compact('receipts'));
    }

    public function create()
    {
        $medicines = Medicine::active()->orderBy('name')->get(['id', 'code', 'name', 'strength', 'unit', 'price']);

        return view('admin.pharmacy.receipts.create', compact('medicines'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier' => ['required', 'string', 'max:191'],
            'received_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.batch_no' => ['nullable', 'string', 'max:50'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.import_price' => ['required', 'integer', 'min:0'],
        ], [
            'items.required' => 'Phiếu nhập cần ít nhất một dòng thuốc.',
        ], [
            'received_date' => 'ngày nhập',
            'items.*.medicine_id' => 'thuốc',
            'items.*.quantity' => 'số lượng',
            'items.*.import_price' => 'giá nhập',
        ]);

        $receipt = $this->pharmacy->receive(
            ['supplier' => $data['supplier'], 'received_date' => $data['received_date'], 'note' => $data['note'] ?? null],
            array_values($data['items']),
            $request->user(),
        );

        return redirect()->route('staff.receipts.show', $receipt)
            ->with('success', 'Đã lập phiếu nhập kho '.$receipt->code.' và cộng dồn tồn kho thành công.');
    }

    public function show(StockReceipt $receipt)
    {
        $receipt->load(['creator', 'batches.medicine']);

        return view('admin.pharmacy.receipts.show', compact('receipt'));
    }
}
