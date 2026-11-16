<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PrescriptionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Pharmacy\Medicine;
use App\Models\Pharmacy\MedicineBatch;
use App\Models\Clinical\Prescription;
use App\Models\Pharmacy\StockReceipt;
use App\Models\Pharmacy\StockTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dược và kho: nhập kho theo lô (TD-08), cấp phát FEFO (TD-06), xuất hủy/kiểm kê (TD-09),
 * cảnh báo (TD-10), báo cáo nhập-xuất-tồn (TD-11).
 */
class PharmacyService
{
    /**
     * Quản lý Nhập kho: cộng trực tiếp vào số lượng tồn kho (stock_quantity).
     *
     * @param  array<int, array{medicine_id:int, batch_no?:string, expiry_date?:string, quantity:int, import_price:int}>  $items
     */
    public function receive(array $header, array $items, User $user): StockReceipt
    {
        return DB::transaction(function () use ($header, $items, $user) {
            $receipt = StockReceipt::create([
                'supplier' => $header['supplier'],
                'received_date' => $header['received_date'],
                'note' => $header['note'] ?? null,
                'created_by' => $user->id,
            ]);

            $total = 0;
            foreach ($items as $item) {
                $qty = (int) $item['quantity'];
                $importPrice = (int) ($item['import_price'] ?? 0);
                $medicine = Medicine::lockForUpdate()->findOrFail($item['medicine_id']);

                // Tinh giản: Cộng trực tiếp vào số lượng tồn kho
                $medicine->increment('stock_quantity', $qty);

                // Giữ bản ghi lô tự động phục vụ lịch sử nhập kho
                $batchNo = !empty($item['batch_no']) ? $item['batch_no'] : ('NK' . date('Ymd') . '-' . $medicine->id);
                $expiryDate = !empty($item['expiry_date']) ? $item['expiry_date'] : now()->addYears(2)->toDateString();

                $batch = MedicineBatch::create([
                    'medicine_id' => $medicine->id,
                    'stock_receipt_id' => $receipt->id,
                    'batch_no' => $batchNo,
                    'expiry_date' => $expiryDate,
                    'quantity' => $qty,
                    'remaining' => $qty,
                    'import_price' => $importPrice,
                ]);

                $this->record($batch, 'import', $qty, $receipt, $user, 'Nhập theo phiếu '.$receipt->code);
                $total += $qty * $importPrice;
            }

            $receipt->update(['total_amount' => $total]);

            return $receipt;
        });
    }

    /**
     * Cấp phát thuốc theo đơn: kiểm tra và trừ trực tiếp số lượng tồn kho (stock_quantity).
     * Đơn phải đã thanh toán và chưa cấp.
     */
    public function dispense(Prescription $prescription, User $user): Prescription
    {
        return DB::transaction(function () use ($prescription, $user) {
            $prescription = Prescription::with(['items.medicine', 'visit.invoice'])->lockForUpdate()->findOrFail($prescription->id);

            if ($prescription->status !== PrescriptionStatus::Pending) {
                throw new BusinessRuleException('Đơn thuốc '.$prescription->code.' đã '.mb_strtolower($prescription->status->label()).'.');
            }

            if ($prescription->visit?->invoice?->status !== InvoiceStatus::Paid) {
                throw new BusinessRuleException('Đơn thuốc chưa được thanh toán, chưa thể cấp phát.');
            }

            $shortages = [];
            foreach ($prescription->items as $item) {
                $medicine = Medicine::lockForUpdate()->find($item->medicine_id);
                $need = (int) $item->quantity;

                if (!$medicine || $medicine->stock_quantity < $need) {
                    $available = $medicine ? $medicine->stock_quantity : 0;
                    $shortages[] = $item->medicine->fullName().' (cần '.$need.', tồn kho hiện còn '.$available.')';
                    continue;
                }

                // Trừ trực tiếp số lượng tồn kho của thuốc
                $medicine->decrement('stock_quantity', $need);

                // Cập nhật lô nếu có bản ghi
                $batch = MedicineBatch::where('medicine_id', $medicine->id)
                    ->where('remaining', '>', 0)
                    ->orderBy('id')
                    ->first();

                if ($batch) {
                    $batch->decrement('remaining', min($need, $batch->remaining));
                }

                $this->record($batch, 'dispense', -$need, $prescription, $user, 'Cấp phát đơn '.$prescription->code);
            }

            if ($shortages) {
                throw new BusinessRuleException('Không đủ số lượng thuốc tồn kho: '.implode('; ', $shortages).'.');
            }

            $prescription->update([
                'status' => PrescriptionStatus::Dispensed,
                'dispensed_by' => $user->id,
                'dispensed_at' => now(),
            ]);

            return $prescription;
        });
    }

    /** TD-09: xuất hủy (thuốc hết hạn, hư hỏng). */
    public function dispose(MedicineBatch $batch, int $quantity, string $reason, User $user): void
    {
        DB::transaction(function () use ($batch, $quantity, $reason, $user) {
            $batch = MedicineBatch::lockForUpdate()->findOrFail($batch->id);
            if ($quantity <= 0 || $quantity > $batch->remaining) {
                throw new BusinessRuleException('Số lượng hủy không hợp lệ (tồn lô hiện tại: '.$batch->remaining.').');
            }
            $batch->decrement('remaining', $quantity);
            $this->record($batch, 'dispose', -$quantity, null, $user, $reason);
        });
    }

    /** TD-09: điều chỉnh tồn sau kiểm kê. */
    public function adjust(MedicineBatch $batch, int $actual, string $reason, User $user): void
    {
        DB::transaction(function () use ($batch, $actual, $reason, $user) {
            $batch = MedicineBatch::lockForUpdate()->findOrFail($batch->id);
            $diff = $actual - $batch->remaining;
            if ($diff === 0) {
                return;
            }
            $batch->update(['remaining' => $actual]);
            $this->record($batch, 'adjust', $diff, null, $user, $reason);
        });
    }

    /** TD-10: cảnh báo tồn kho dưới định mức tối thiểu. */
    public function alerts(): array
    {
        $low = Medicine::active()->get()
            ->filter(fn (Medicine $m) => $m->stock_quantity <= $m->min_stock)
            ->values();

        $nearExpiry = collect();
        $expired = collect();

        return compact('low', 'nearExpiry', 'expired');
    }

    /** TD-11: báo cáo nhập - xuất - tồn theo thuốc trong khoảng thời gian. */
    public function stockReport(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $opening = StockTransaction::where('created_at', '<', $start)
            ->groupBy('medicine_id')->selectRaw('medicine_id, SUM(quantity) as qty')->pluck('qty', 'medicine_id');

        $period = StockTransaction::whereBetween('created_at', [$start, $end])
            ->groupBy('medicine_id', 'type')
            ->selectRaw('medicine_id, type, SUM(quantity) as qty')
            ->get()
            ->groupBy('medicine_id');

        return Medicine::orderBy('name')->get()->map(function (Medicine $m) use ($opening, $period) {
            $rows = $period->get($m->id, collect())->pluck('qty', 'type');
            $open = (int) ($opening[$m->id] ?? 0);
            $import = (int) ($rows['import'] ?? 0);
            $dispense = -(int) ($rows['dispense'] ?? 0);
            $dispose = -(int) ($rows['dispose'] ?? 0);
            $adjust = (int) ($rows['adjust'] ?? 0);

            return [
                'medicine' => $m,
                'opening' => $open,
                'import' => $import,
                'dispense' => $dispense,
                'dispose' => $dispose,
                'adjust' => $adjust,
                'closing' => $open + $import - $dispense - $dispose + $adjust,
            ];
        })->filter(fn ($r) => $r['opening'] || $r['import'] || $r['dispense'] || $r['dispose'] || $r['adjust'])->values();
    }

    protected function record(MedicineBatch $batch, string $type, int $qty, ?object $reference, User $user, ?string $note = null): void
    {
        StockTransaction::create([
            'medicine_id' => $batch->medicine_id,
            'medicine_batch_id' => $batch->id,
            'type' => $type,
            'quantity' => $qty,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->id,
            'note' => $note,
            'user_id' => $user->id,
        ]);
    }
}
