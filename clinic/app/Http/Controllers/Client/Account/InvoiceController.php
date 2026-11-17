<?php

namespace App\Http\Controllers\Client\Account;

use App\Http\Controllers\Client\Account\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Billing\Invoice;
use App\Models\Billing\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/** BN-16: lịch sử thanh toán, xem và tải hóa đơn PDF. */
class InvoiceController extends Controller
{
    use AuthorizesOwnership;

    public function index(Request $request)
    {
        $user = $request->user();

        $invoices = Invoice::with(['patient', 'visit.doctor.user', 'visit.specialty'])
            ->ownedBy($user)
            ->latest()
            ->paginate(10);

        // Các khoản trả trước / đặt cọc online cho lịch hẹn
        $deposits = Payment::with(['appointment', 'patient'])
            ->whereNotNull('appointment_id')
            ->whereHas('patient', fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->take(10)
            ->get();

        return view('client.account.invoices.index', compact('invoices', 'deposits'));
    }

    public function show(Invoice $invoice)
    {
        $this->authorizeOwner($invoice);
        $invoice->load(['items', 'payments', 'patient', 'visit.doctor.user', 'visit.specialty', 'cashier']);

        return view('client.account.invoices.show', compact('invoice'));
    }

    public function pdf(Invoice $invoice)
    {
        $this->authorizeOwner($invoice);
        $invoice->load(['items', 'payments', 'patient', 'visit.doctor.user', 'visit.specialty', 'cashier']);

        return Pdf::loadView('client.account.invoices.pdf', compact('invoice'))
            ->setPaper('a5', 'portrait')
            ->download('hoa-don-'.$invoice->code.'.pdf');
    }
}
