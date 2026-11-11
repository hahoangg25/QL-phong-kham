<?php

namespace App\Http\Controllers\Admin\Cashier;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Billing\Invoice;
use App\Models\Billing\InvoiceItem;
use App\Models\Billing\Payment;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Thu ngân: danh sách hóa đơn (TD-01), thu tiền (TD-02), in (TD-03), hủy/hoàn (TD-04), báo cáo ca (TD-12). */
class CashierController extends Controller
{
    public function __construct(protected BillingService $billing) {}

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'unpaid');
        $search = trim((string) $request->input('q'));

        $invoices = Invoice::with(['patient', 'visit.doctor.user', 'cashier'])
            ->when($tab === 'unpaid', fn ($q) => $q->where('status', InvoiceStatus::Unpaid->value))
            ->when($tab === 'paid', fn ($q) => $q->where('status', InvoiceStatus::Paid->value)->whereDate('paid_at', today()))
            ->when($tab === 'closed', fn ($q) => $q->whereIn('status', [InvoiceStatus::Cancelled->value, InvoiceStatus::Refunded->value]))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%{$search}%")
                ->orWhereHas('patient', fn ($p) => $p->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "{$search}%")
                    ->orWhere('code', $search))))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->input('date')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $unpaid = Invoice::where('status', InvoiceStatus::Unpaid->value)->get(['total', 'paid_amount']);
        $stats = [
            'unpaid_count' => $unpaid->count(),
            'unpaid_total' => $unpaid->sum(fn ($i) => max(0, $i->total - $i->paid_amount)),
            'paid_today' => $this->netCollected(today()->startOfDay(), today()->endOfDay()),
            'paid_today_count' => Invoice::where('status', InvoiceStatus::Paid->value)->whereDate('paid_at', today())->count(),
        ];

        return view('admin.cashier.index', compact('invoices', 'tab', 'stats'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['patient', 'visit.doctor.user', 'visit.specialty', 'visit.diagnoses', 'items', 'payments.receiver', 'cashier', 'canceller', 'creator']);

        $groups = $invoice->items->groupBy('item_type');

        return view('admin.cashier.show', [
            'invoice' => $invoice,
            'groups' => $groups,
            'types' => InvoiceItem::TYPES,
            'methods' => PaymentMethod::counterOptions(),
        ]);
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(PaymentMethod::counterOptions()))],
            'transaction_no' => ['nullable', 'required_if:method,'.PaymentMethod::Transfer->value, 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'transaction_no.required_if' => 'Vui lòng nhập mã giao dịch chuyển khoản.',
        ]);

        $payment = $this->billing->pay(
            $invoice,
            PaymentMethod::from($data['method']),
            $request->user(),
            $data['transaction_no'] ?? null,
            $data['note'] ?? null,
        );

        return redirect()->route('staff.cashier.show', $invoice)
            ->with('success', 'Đã thu '.money($payment->amount).' cho hóa đơn '.$invoice->code.'.')
            ->with('print_url', route('staff.cashier.print', [$invoice, 'auto' => 1]));
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->billing->cancel($invoice, $data['reason'], $request->user());

        return redirect()->route('staff.cashier.show', $invoice)->with('success', 'Đã hủy hóa đơn '.$invoice->code.'.');
    }

    public function refund(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $refund = $this->billing->refund($invoice, $data['reason'], $request->user());

        return redirect()->route('staff.cashier.show', $invoice)
            ->with('success', 'Đã hoàn '.money($refund->amount).' cho hóa đơn '.$invoice->code.'.');
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['patient', 'visit.doctor.user', 'visit.specialty', 'items', 'payments', 'cashier']);

        return view('admin.cashier.print', [
            'invoice' => $invoice,
            'groups' => $invoice->items->groupBy('item_type'),
            'types' => InvoiceItem::TYPES,
            'amountInWords' => self::amountInWords((int) $invoice->total),
        ]);
    }

    /** TD-12: báo cáo thu theo người thu, phương thức, ngày. */
    public function report(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'cashier_id' => ['nullable', 'integer'],
        ]);

        $from = Carbon::parse($request->input('from', today()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', $from->toDateString()))->endOfDay();

        $payments = Payment::with(['invoice', 'patient', 'receiver', 'appointment'])
            ->where('status', PaymentStatus::Success->value)
            ->whereBetween('paid_at', [$from, $to])
            ->when($request->filled('cashier_id'), fn ($q) => $q->where('received_by', $request->integer('cashier_id')))
            ->orderBy('paid_at')
            ->get();

        $signed = fn (Payment $p) => $p->type === 'refund' ? -$p->amount : $p->amount;

        $byCashier = $payments->groupBy(fn ($p) => $p->receiver?->name ?? 'Thanh toán online')
            ->map(fn ($items) => ['count' => $items->count(), 'total' => $items->sum($signed)])
            ->sortByDesc('total');

        $byMethod = $payments->groupBy(fn ($p) => $p->method?->label() ?? '-')
            ->map(fn ($items) => ['count' => $items->count(), 'total' => $items->sum($signed)]);

        $byDay = $payments->groupBy(fn ($p) => $p->paid_at->format('Y-m-d'))
            ->map(fn ($items) => [
                'count' => $items->count(),
                'income' => $items->where('type', '!=', 'refund')->sum('amount'),
                'refund' => $items->where('type', 'refund')->sum('amount'),
            ])->sortKeys();

        $summary = [
            'income' => $payments->where('type', '!=', 'refund')->sum('amount'),
            'refund' => $payments->where('type', 'refund')->sum('amount'),
            'count' => $payments->count(),
        ];
        $summary['net'] = $summary['income'] - $summary['refund'];

        $cashiers = User::role([Role::Receptionist->value, Role::Admin->value])->orderBy('name')->get(['id', 'name']);

        return view('admin.cashier.report', compact('payments', 'byCashier', 'byMethod', 'byDay', 'summary', 'cashiers', 'from', 'to'));
    }

    protected function netCollected(Carbon $from, Carbon $to): int
    {
        $payments = Payment::where('status', PaymentStatus::Success->value)->whereBetween('paid_at', [$from, $to])->get(['type', 'amount']);

        return (int) ($payments->where('type', '!=', 'refund')->sum('amount') - $payments->where('type', 'refund')->sum('amount'));
    }

    /** Đọc số tiền bằng chữ (tiếng Việt) cho biên lai. */
    public static function amountInWords(int $number): string
    {
        if ($number === 0) {
            return 'Không đồng';
        }

        $digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
        $units = ['', ' nghìn', ' triệu', ' tỷ', ' nghìn tỷ', ' triệu tỷ'];

        $readTriple = function (int $n, bool $full) use ($digits): string {
            $h = intdiv($n, 100);
            $t = intdiv($n % 100, 10);
            $u = $n % 10;
            $out = [];

            if ($full || $h > 0) {
                $out[] = $digits[$h].' trăm';
            }
            if ($t > 1) {
                $out[] = $digits[$t].' mươi';
            } elseif ($t === 1) {
                $out[] = 'mười';
            } elseif (($full || $h > 0) && $u > 0) {
                $out[] = 'lẻ';
            }
            if ($u > 0) {
                $out[] = match (true) {
                    $u === 1 && $t > 1 => 'mốt',
                    $u === 5 && $t > 0 => 'lăm',
                    $u === 4 && $t > 1 => 'tư',
                    default => $digits[$u],
                };
            }

            return implode(' ', $out);
        };

        $groups = [];
        while ($number > 0) {
            $groups[] = $number % 1000;
            $number = intdiv($number, 1000);
        }

        $parts = [];
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            if ($groups[$i] === 0) {
                continue;
            }
            $parts[] = $readTriple($groups[$i], $i < count($groups) - 1).$units[$i];
        }

        $text = implode(' ', $parts);

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1).' đồng';
    }
}
