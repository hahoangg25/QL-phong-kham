<?php

namespace App\Http\Controllers\Admin\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy\Medicine;
use App\Models\Pharmacy\MedicineBatch;
use App\Models\Pharmacy\StockTransaction;
use App\Services\PharmacyService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Tồn kho & cảnh báo (TD-10), xuất hủy/kiểm kê (TD-09), báo cáo nhập-xuất-tồn (TD-11). */
class InventoryController extends Controller
{
    public function __construct(protected PharmacyService $pharmacy) {}

    public function index(Request $request)
    {
        $alerts = $this->pharmacy->alerts();
        $search = trim((string) $request->input('q'));

        $medicines = Medicine::query()
            ->withUsableStock()
            ->withMin(['batches as nearest_expiry' => fn ($q) => $q->where('remaining', '>', 0)
                ->whereDate('expiry_date', '>=', today())], 'expiry_date')
            ->search($search ?: null)
            ->when($request->input('filter') === 'low', fn ($q) => $q->whereIn('id', $alerts['low']->pluck('id')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pharmacy.inventory.index', compact('alerts', 'medicines'));
    }

    public function show(Medicine $medicine)
    {
        $medicine->loadSum(['batches as usable_stock' => fn ($q) => $q->where('remaining', '>', 0)
            ->whereDate('expiry_date', '>=', today())], 'remaining');

        $batches = $medicine->batches()->with('receipt')->orderBy('expiry_date')->orderBy('id')->get();

        $transactions = StockTransaction::with(['batch', 'user', 'reference'])
            ->where('medicine_id', $medicine->id)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pharmacy.inventory.show', compact('medicine', 'batches', 'transactions'));
    }

    public function dispose(Request $request, MedicineBatch $batch)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['quantity' => 'số lượng hủy']);

        $this->pharmacy->dispose($batch, (int) $data['quantity'], 'Xuất hủy: '.$data['reason'], $request->user());

        return redirect()->route('staff.inventory.show', $batch->medicine_id)
            ->with('success', 'Đã xuất hủy '.$data['quantity'].' '.$batch->medicine->unit.' lô '.$batch->batch_no.'.');
    }

    public function adjust(Request $request, MedicineBatch $batch)
    {
        $data = $request->validate([
            'actual' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['actual' => 'số lượng thực tế']);

        $before = $batch->remaining;
        $this->pharmacy->adjust($batch, (int) $data['actual'], 'Kiểm kê: '.$data['reason'], $request->user());

        $diff = (int) $data['actual'] - $before;
        $message = $diff === 0
            ? 'Số lượng thực tế khớp với sổ sách, không cần điều chỉnh.'
            : 'Đã điều chỉnh tồn lô '.$batch->batch_no.' từ '.$before.' thành '.$data['actual'].' ('.($diff > 0 ? '+' : '').$diff.').';

        return redirect()->route('staff.inventory.show', $batch->medicine_id)->with('success', $message);
    }

    public function report(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->input('from', today()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->input('to', today()->toDateString()));
        $rows = $this->pharmacy->stockReport($from, $to);

        if ($request->input('export') === 'csv') {
            return $this->csv($rows, $from, $to);
        }

        $totals = [
            'opening' => $rows->sum('opening'),
            'import' => $rows->sum('import'),
            'dispense' => $rows->sum('dispense'),
            'dispose' => $rows->sum('dispose'),
            'adjust' => $rows->sum('adjust'),
            'closing' => $rows->sum('closing'),
        ];

        return view('admin.pharmacy.inventory.report', compact('rows', 'from', 'to', 'totals'));
    }

    protected function csv($rows, Carbon $from, Carbon $to)
    {
        $filename = 'bao-cao-nhap-xuat-ton-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['BÁO CÁO NHẬP - XUẤT - TỒN KHO THUỐC', 'Từ '.$from->format('d/m/Y').' đến '.$to->format('d/m/Y')]);
            fputcsv($out, ['Mã thuốc', 'Tên thuốc', 'Hàm lượng', 'Đơn vị', 'Tồn đầu kỳ', 'Nhập trong kỳ', 'Xuất cấp phát', 'Xuất hủy', 'Điều chỉnh kiểm kê', 'Tồn cuối kỳ']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['medicine']->code, $r['medicine']->name, $r['medicine']->strength, $r['medicine']->unit,
                    $r['opening'], $r['import'], $r['dispense'], $r['dispose'], $r['adjust'], $r['closing'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
