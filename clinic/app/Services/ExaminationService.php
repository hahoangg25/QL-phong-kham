<?php

namespace App\Services;

use App\Enums\PrescriptionStatus;
use App\Enums\Role;
use App\Enums\ServiceOrderStatus;
use App\Enums\VisitStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Clinical\Diagnosis;
use App\Models\Pharmacy\Medicine;
use App\Models\Clinical\Prescription;
use App\Models\Catalog\Service;
use App\Models\Clinical\ServiceOrder;
use App\Models\Scheduling\TimeSlot;
use App\Models\User;
use App\Models\Clinical\Visit;
use App\Models\Clinical\VisitAddendum;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Khám bệnh: ghi nhận lâm sàng (BS-05), chẩn đoán ICD-10 (BS-06), chỉ định CLS (BS-07),
 * kê đơn (BS-09), hẹn tái khám (BS-11), hoàn thành và khóa bệnh án (BS-12, BR-07, BR-08).
 */
class ExaminationService
{
    public function __construct(
        protected QueueService $queue,
        protected BillingService $billing,
        protected BookingService $booking,
    ) {}

    /** BR-07: chỉ bác sĩ phụ trách lượt khám được ghi bệnh án, và bệnh án chưa khóa. */
    public function assertEditable(Visit $visit, User $user): void
    {
        if ($visit->isLocked()) {
            throw new BusinessRuleException('Bệnh án đã hoàn thành và bị khóa. Chỉ có thể ghi bổ sung (BR-08).');
        }

        if (! $user->doctor || $user->doctor->id !== $visit->doctor_id) {
            abort(403, 'Chỉ bác sĩ phụ trách lượt khám được ghi bệnh án (BR-07).');
        }

        if (! in_array($visit->status, [VisitStatus::InProgress, VisitStatus::AwaitingResults], true)) {
            throw new BusinessRuleException('Cần gọi bệnh nhân vào khám trước khi ghi bệnh án.');
        }
    }

    /** BS-05 */
    public function saveClinical(Visit $visit, array $data, User $user): Visit
    {
        $this->assertEditable($visit, $user);

        $visit->update(collect($data)->only([
            'reason', 'symptoms', 'pulse', 'bp_systolic', 'bp_diastolic', 'temperature', 'respiratory_rate',
            'spo2', 'weight', 'height', 'clinical_notes', 'conclusion', 'advice', 'follow_up_date',
        ])->all());

        return $visit;
    }

    /**
     * BS-06: lưu danh sách chẩn đoán (một bệnh chính, nhiều bệnh kèm theo).
     *
     * @param  array<int, array{icd10_code: string, name: string, is_primary?: bool, note?: string}>  $items
     */
    public function syncDiagnoses(Visit $visit, array $items, User $user): void
    {
        $this->assertEditable($visit, $user);

        DB::transaction(function () use ($visit, $items) {
            $visit->diagnoses()->delete();
            $hasPrimary = false;

            foreach ($items as $i => $item) {
                if (empty($item['icd10_code'])) {
                    continue;
                }
                $primary = ! $hasPrimary && (! empty($item['is_primary']) || $i === 0);
                $hasPrimary = $hasPrimary || $primary;

                Diagnosis::create([
                    'visit_id' => $visit->id,
                    'icd10_code' => strtoupper(trim($item['icd10_code'])),
                    'name' => $item['name'],
                    'is_primary' => $primary,
                    'note' => $item['note'] ?? null,
                ]);
            }
        });
    }

    /** BS-07: chỉ định dịch vụ CLS; lượt khám chuyển "Chờ kết quả CLS". */
    public function orderServices(Visit $visit, array $serviceIds, ?string $note, User $user): void
    {
        $this->assertEditable($visit, $user);

        $services = Service::active()->paraclinical()->whereIn('id', $serviceIds)->get();
        if ($services->isEmpty()) {
            throw new BusinessRuleException('Vui lòng chọn ít nhất một dịch vụ cận lâm sàng.');
        }

        DB::transaction(function () use ($visit, $services, $note, $user) {
            foreach ($services as $service) {
                ServiceOrder::create([
                    'visit_id' => $visit->id,
                    'service_id' => $service->id,
                    'ordered_by' => $user->id,
                    'price' => $service->price,
                    'status' => ServiceOrderStatus::Pending,
                    'note' => $note,
                ]);
            }

            $visit->update(['status' => VisitStatus::AwaitingResults]);
            $this->queue->syncAppointment($visit, $user, 'Chỉ định cận lâm sàng');
        });

        $techs = User::role(Role::Doctor->value)->where('is_active', true)->get();
        rescue(fn () => Notification::send($techs, new GeneralNotification(
            'Chỉ định cận lâm sàng mới',
            $services->count().' dịch vụ cho bệnh nhân '.$visit->patient->full_name,
            route('staff.lab.index'),
        )));
    }

    public function cancelOrder(ServiceOrder $order, User $user): void
    {
        $this->assertEditable($order->visit, $user);

        if ($order->status !== ServiceOrderStatus::Pending) {
            throw new BusinessRuleException('Chỉ hủy được chỉ định chưa thực hiện.');
        }

        $order->update(['status' => ServiceOrderStatus::Cancelled]);
        $this->resumeIfResultsReady($order->visit, $user);
    }

    /** Khi tất cả chỉ định đã có kết quả, lượt khám quay lại "Đang khám" để bác sĩ kết luận. */
    public function resumeIfResultsReady(Visit $visit, ?User $by = null): void
    {
        $visit->refresh();

        $pending = $visit->serviceOrders()
            ->whereIn('status', [ServiceOrderStatus::Pending->value, ServiceOrderStatus::InProgress->value])
            ->exists();

        if (! $pending && $visit->status === VisitStatus::AwaitingResults) {
            $visit->update(['status' => VisitStatus::InProgress]);
            $this->queue->syncAppointment($visit, $by, 'Đã có đủ kết quả cận lâm sàng');
        }
    }

    /**
     * BS-09: kê đơn. Trả về danh sách cảnh báo tồn kho/dị ứng (BR-10) để hiển thị cho bác sĩ.
     *
     * @param  array<int, array{medicine_id: int, quantity: int, dosage: string, usage?: string, days?: int}>  $items
     * @return array<int, string>
     */
    public function savePrescription(Visit $visit, array $items, ?string $note, User $user): array
    {
        $this->assertEditable($visit, $user);

        $prescription = $visit->prescription;
        if ($prescription && ! $prescription->isEditable()) {
            throw new BusinessRuleException('Đơn thuốc đã được cấp phát, không thể sửa (BR-10).');
        }

        $items = collect($items)->filter(fn ($i) => ! empty($i['medicine_id']) && (int) ($i['quantity'] ?? 0) > 0)->values();
        $medicines = Medicine::withUsableStock()->whereIn('id', $items->pluck('medicine_id'))->get()->keyBy('id');

        DB::transaction(function () use ($visit, $items, $note, $medicines, &$prescription) {
            if ($items->isEmpty()) {
                $prescription?->delete();
                $prescription = null;

                return;
            }

            $prescription ??= Prescription::create([
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'doctor_id' => $visit->doctor_id,
                'status' => PrescriptionStatus::Pending,
            ]);
            $prescription->update(['note' => $note]);
            $prescription->items()->delete();

            foreach ($items as $item) {
                $medicine = $medicines[$item['medicine_id']] ?? null;
                if (! $medicine) {
                    continue;
                }
                $qty = (int) $item['quantity'];
                $prescription->items()->create([
                    'medicine_id' => $medicine->id,
                    'quantity' => $qty,
                    'dosage' => $item['dosage'] ?? '',
                    'usage' => $item['usage'] ?? null,
                    'days' => max(1, (int) ($item['days'] ?? 1)),
                    'unit_price' => $medicine->price,
                    'amount' => $qty * $medicine->price,
                ]);
            }
        });

        return $this->prescriptionWarnings($visit, $items->all());
    }

    /** BR-10: cảnh báo thuốc không đủ tồn hoặc trùng với tiền sử dị ứng. */
    public function prescriptionWarnings(Visit $visit, array $items): array
    {
        $warnings = [];
        $allergies = array_map('mb_strtolower', $visit->patient->allergyList());
        $medicines = Medicine::withUsableStock()->whereIn('id', collect($items)->pluck('medicine_id'))->get()->keyBy('id');

        foreach ($items as $item) {
            $m = $medicines[$item['medicine_id']] ?? null;
            if (! $m) {
                continue;
            }
            if ((int) $item['quantity'] > $m->usableStock()) {
                $warnings[] = "Thuốc {$m->fullName()}: kê {$item['quantity']} {$m->unit} nhưng tồn kho còn hạn chỉ còn {$m->usableStock()}.";
            }
            foreach ($allergies as $a) {
                if ($a !== '' && (str_contains(mb_strtolower($m->name), $a) || str_contains(mb_strtolower((string) $m->active_ingredient), $a))) {
                    $warnings[] = "CẢNH BÁO DỊ ỨNG: bệnh nhân có tiền sử dị ứng \"{$a}\" - thuốc {$m->fullName()} ({$m->active_ingredient}).";
                }
            }
        }

        return $warnings;
    }

    /**
     * BS-12: hoàn thành lượt khám - khóa bệnh án, lập hóa đơn (TD-01), tạo lịch tái khám nếu có (BS-11).
     */
    public function complete(Visit $visit, User $user): Visit
    {
        $this->assertEditable($visit, $user);

        if (! $visit->diagnoses()->where('is_primary', true)->exists()) {
            throw new BusinessRuleException('Cần nhập chẩn đoán bệnh chính (ICD-10) trước khi hoàn thành lượt khám.');
        }

        if ($visit->serviceOrders()->whereIn('status', [ServiceOrderStatus::Pending->value, ServiceOrderStatus::InProgress->value])->exists()) {
            throw new BusinessRuleException('Còn chỉ định cận lâm sàng chưa có kết quả. Hủy chỉ định hoặc chờ kết quả trước khi hoàn thành.');
        }

        DB::transaction(function () use ($visit, $user) {
            $visit->update([
                'status' => VisitStatus::Completed,
                'completed_at' => now(),
                'locked_at' => now(),
            ]);
            $this->queue->syncAppointment($visit, $user, 'Hoàn thành lượt khám');
            $this->billing->createForVisit($visit, $user);
        });

        $this->queue->broadcast($visit->doctor_id, 'completed');
        $followUp = $this->bookFollowUp($visit, $user);

        $owner = $visit->patient->user;
        if ($owner) {
            rescue(fn () => $owner->notify(new GeneralNotification(
                'Đã hoàn thành lượt khám',
                'Kết quả khám, đơn thuốc của '.$visit->patient->full_name.' đã có trong lịch sử khám.'
                    .($followUp ? ' Lịch tái khám: '.$followUp->scheduled_at->format('H:i d/m/Y').'.' : ''),
                route('account.visits.show', $visit),
            )));
        }

        return $visit;
    }

    /** BS-11: tự đặt lịch tái khám vào slot trống đầu tiên của bác sĩ trong ngày hẹn. */
    protected function bookFollowUp(Visit $visit, User $user): ?\App\Models\Scheduling\Appointment
    {
        if (! $visit->follow_up_date || $visit->follow_up_date->lte(today())) {
            return null;
        }

        $slots = TimeSlot::where('doctor_id', $visit->doctor_id)->whereDate('date', $visit->follow_up_date)
            ->where('is_active', true)->orderBy('start_time')->get();

        foreach ($slots as $slot) {
            try {
                return $this->booking->bookByStaff($user, $visit->patient, $slot, 'Tái khám theo hẹn của bác sĩ', 'follow_up');
            } catch (BusinessRuleException) {
                continue; // slot đầy hoặc đã có lịch -> thử slot sau
            }
        }

        return null;
    }

    /** BR-08: ghi bổ sung vào bệnh án đã khóa (có ghi vết người, thời điểm). */
    public function addAddendum(Visit $visit, string $content, User $user): VisitAddendum
    {
        if (! $visit->isLocked()) {
            throw new BusinessRuleException('Bệnh án chưa khóa, hãy chỉnh sửa trực tiếp.');
        }

        if (! $user->isAdmin() && $user->doctor?->id !== $visit->doctor_id) {
            abort(403, 'Chỉ bác sĩ phụ trách được ghi bổ sung bệnh án.');
        }

        $addendum = $visit->addenda()->create(['user_id' => $user->id, 'content' => $content]);

        activity('medical_record')->performedOn($visit)->causedBy($user)
            ->withProperties(['addendum_id' => $addendum->id])
            ->log('Ghi bổ sung bệnh án đã khóa');

        return $addendum;
    }
}
