<?php

namespace App\Http\Controllers\Admin\Management;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Clinical\Diagnosis;
use App\Models\Catalog\Doctor;
use App\Models\Billing\Invoice;
use App\Models\Billing\InvoiceItem;
use App\Models\Billing\Payment;
use App\Models\Catalog\Specialty;
use App\Models\Clinical\Visit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * QT-15: dashboard thống kê (lượt khám, doanh thu, no-show, hiệu suất bác sĩ).
 * QT-16: xuất báo cáo CSV (mở bằng Excel) và PDF theo bộ lọc.
 * Tổng hợp bằng PHP để chạy được trên cả MySQL và SQLite.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.management.reports.index', $this->build($request) + [
            'specialties' => Specialty::orderBy('sort_order')->get(),
            'doctors' => Doctor::with('user')->orderBy('specialty_id')->get(),
        ]);
    }

    public function export(Request $request)
    {
        $data = $this->build($request);
        $suffix = $data['from']->format('Ymd').'-'.$data['to']->format('Ymd');

        if ($request->input('format') === 'pdf') {
            return Pdf::loadView('admin.management.reports.pdf', $data)
                ->setPaper('a4', 'landscape')
                ->download("bao-cao-phong-kham-{$suffix}.pdf");
        }

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel đọc đúng tiếng Việt

            fputcsv($out, ['BÁO CÁO HOẠT ĐỘNG PHÒNG KHÁM']);
            fputcsv($out, ['Từ ngày', $data['from']->format('d/m/Y'), 'Đến ngày', $data['to']->format('d/m/Y')]);
            if ($data['filterLabel']) {
                fputcsv($out, ['Bộ lọc', $data['filterLabel']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['CHỈ SỐ TỔNG HỢP']);
            foreach ($this->kpiRows($data['kpi']) as $row) {
                fputcsv($out, $row);
            }
            fputcsv($out, []);

            fputcsv($out, ['THEO NGÀY']);
            fputcsv($out, ['Ngày', 'Lượt khám', 'Doanh thu (đ)']);
            foreach ($data['daily'] as $d) {
                fputcsv($out, [$d['label'], $d['visits'], $d['revenue']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['THEO CHUYÊN KHOA']);
            fputcsv($out, ['Chuyên khoa', 'Lượt khám', 'Doanh thu (đ)']);
            foreach ($data['bySpecialty'] as $s) {
                fputcsv($out, [$s['name'], $s['visits'], $s['revenue']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['HIỆU SUẤT BÁC SĨ']);
            fputcsv($out, ['Bác sĩ', 'Chuyên khoa', 'Lượt khám', 'Doanh thu (đ)', 'Điểm đánh giá', 'Tỷ lệ vắng mặt (%)', 'Thời gian khám TB (phút)']);
            foreach ($data['doctorStats'] as $d) {
                fputcsv($out, [$d['name'], $d['specialty'], $d['visits'], $d['revenue'], $d['rating'], $d['no_show_rate'], $d['avg_minutes']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['TOP CHẨN ĐOÁN']);
            fputcsv($out, ['Mã ICD-10', 'Tên bệnh', 'Số ca']);
            foreach ($data['topDiagnoses'] as $d) {
                fputcsv($out, [$d['code'], $d['name'], $d['count']]);
            }

            fclose($out);
        }, "bao-cao-phong-kham-{$suffix}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------

    protected function build(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'specialty_id' => ['nullable', 'integer'],
            'doctor_id' => ['nullable', 'integer'],
        ]);

        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->startOfDay() : today();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $to->copy()->subDays(29);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $specialtyId = $request->integer('specialty_id') ?: null;
        $doctorId = $request->integer('doctor_id') ?: null;

        $scope = function ($query, string $doctorCol = 'doctor_id', string $specialtyCol = 'specialty_id') use ($specialtyId, $doctorId) {
            return $query
                ->when($specialtyId, fn ($q) => $q->where($specialtyCol, $specialtyId))
                ->when($doctorId, fn ($q) => $q->where($doctorCol, $doctorId));
        };

        // Lượt khám hoàn thành
        $visits = $scope(Visit::query())
            ->where('status', VisitStatus::Completed->value)
            ->whereDate('visit_date', '>=', $from)->whereDate('visit_date', '<=', $to)
            ->get(['id', 'visit_date', 'doctor_id', 'specialty_id', 'started_at', 'completed_at']);

        // Thực thu: thanh toán hóa đơn + đặt cọc online - hoàn tiền
        $payments = Payment::with(['invoice.visit:id,doctor_id,specialty_id', 'appointment:id,doctor_id,specialty_id'])
            ->where('status', PaymentStatus::Success->value)
            ->whereBetween('paid_at', [$start, $end])
            ->get()
            ->filter(function (Payment $p) use ($specialtyId, $doctorId) {
                if (! $specialtyId && ! $doctorId) {
                    return true;
                }
                $owner = $p->invoice?->visit ?? $p->appointment;

                return $owner
                    && (! $specialtyId || $owner->specialty_id === $specialtyId)
                    && (! $doctorId || $owner->doctor_id === $doctorId);
            });
        $signed = fn (Payment $p) => $p->type === 'refund' ? -(int) $p->amount : (int) $p->amount;
        $revenue = (int) $payments->sum($signed);

        // Lịch hẹn theo ngày khám
        $appointments = $scope(Appointment::query())
            ->whereDate('appointment_date', '>=', $from)->whereDate('appointment_date', '<=', $to)
            ->get(['id', 'doctor_id', 'specialty_id', 'status', 'source']);
        $byStatus = $appointments->countBy(fn ($a) => $a->status->value);
        $completedAppts = (int) ($byStatus[AppointmentStatus::Completed->value] ?? 0);
        $noShows = (int) ($byStatus[AppointmentStatus::NoShow->value] ?? 0);
        $cancelled = (int) ($byStatus[AppointmentStatus::Cancelled->value] ?? 0);

        $kpi = [
            'visits' => $visits->count(),
            'revenue' => $revenue,
            'avg_revenue' => $visits->count() ? (int) round($revenue / $visits->count()) : 0,
            'appointments' => $appointments->count(),
            'no_show_rate' => $this->rate($noShows, $completedAppts + $noShows),
            'cancel_rate' => $this->rate($cancelled, $appointments->count()),
            'online_rate' => $this->rate($appointments->where('source', 'online')->count(), $appointments->count()),
            'walk_in' => $scope(Visit::query())->where('type', 'walk_in')->where('status', VisitStatus::Completed->value)
                ->whereDate('visit_date', '>=', $from)->whereDate('visit_date', '<=', $to)->count(),
            'no_shows' => $noShows,
            'cancelled' => $cancelled,
            'status_breakdown' => collect(AppointmentStatus::cases())
                ->mapWithKeys(fn ($s) => [$s->label() => (int) ($byStatus[$s->value] ?? 0)])
                ->filter()->all(),
        ];

        // Theo ngày
        $visitsByDay = $visits->countBy(fn ($v) => $v->visit_date->toDateString());
        $revenueByDay = $payments->groupBy(fn ($p) => $p->paid_at->toDateString())->map(fn ($g) => (int) $g->sum($signed));
        $daily = collect(CarbonPeriod::create($from, $to))->map(fn (Carbon $d) => [
            'date' => $d->toDateString(),
            'label' => $d->format('d/m'),
            'visits' => (int) ($visitsByDay[$d->toDateString()] ?? 0),
            'revenue' => (int) ($revenueByDay[$d->toDateString()] ?? 0),
        ])->values();

        // Theo chuyên khoa
        $specialties = Specialty::orderBy('sort_order')->get()->keyBy('id');
        $revenueBySpecialty = $payments->groupBy(fn ($p) => ($p->invoice?->visit ?? $p->appointment)?->specialty_id)->map(fn ($g) => (int) $g->sum($signed));
        $bySpecialty = $visits->countBy('specialty_id')
            ->map(fn ($count, $id) => [
                'name' => $specialties[$id]->name ?? 'Khác',
                'visits' => $count,
                'revenue' => (int) ($revenueBySpecialty[$id] ?? 0),
            ])
            ->sortByDesc('visits')->values();

        // Doanh thu theo loại (phí khám / CLS / thuốc) từ hóa đơn đã thanh toán
        $paidInvoices = Invoice::with('visit:id,doctor_id,specialty_id')
            ->where('status', InvoiceStatus::Paid->value)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['id', 'visit_id', 'subtotal', 'discount', 'paid_at'])
            ->filter(fn ($i) => (! $specialtyId || $i->visit?->specialty_id === $specialtyId) && (! $doctorId || $i->visit?->doctor_id === $doctorId));
        $itemSums = InvoiceItem::whereIn('invoice_id', $paidInvoices->pluck('id'))->get(['item_type', 'amount'])
            ->groupBy('item_type')->map(fn ($g) => (int) $g->sum('amount'));
        $byItemType = collect(InvoiceItem::TYPES)->map(fn ($label, $type) => [
            'label' => $label,
            'amount' => (int) ($itemSums[$type] ?? 0),
        ])->values();

        // Hiệu suất bác sĩ
        $doctors = Doctor::with(['user', 'specialty'])
            ->when($specialtyId, fn ($q) => $q->where('specialty_id', $specialtyId))
            ->when($doctorId, fn ($q) => $q->whereKey($doctorId))
            ->get();
        $invoiceByDoctor = $paidInvoices->groupBy(fn ($i) => $i->visit?->doctor_id)
            ->map(fn ($g) => (int) $g->sum(fn ($i) => $i->subtotal - $i->discount));
        $doctorStats = $doctors->map(function (Doctor $d) use ($visits, $appointments, $invoiceByDoctor) {
            $dv = $visits->where('doctor_id', $d->id);
            $da = $appointments->where('doctor_id', $d->id);
            $done = $da->filter(fn ($a) => $a->status === AppointmentStatus::Completed)->count();
            $ns = $da->filter(fn ($a) => $a->status === AppointmentStatus::NoShow)->count();
            $minutes = $dv->filter(fn ($v) => $v->started_at && $v->completed_at)
                ->map(fn ($v) => $v->started_at->diffInMinutes($v->completed_at));

            return [
                'id' => $d->id,
                'name' => $d->display_name,
                'specialty' => $d->specialty?->name,
                'visits' => $dv->count(),
                'revenue' => (int) ($invoiceByDoctor[$d->id] ?? 0),
                'rating' => round((float) $d->rating_avg, 2),
                'rating_count' => $d->rating_count,
                'no_show_rate' => $this->rate($ns, $done + $ns),
                'avg_minutes' => $minutes->isNotEmpty() ? round($minutes->avg(), 1) : null,
            ];
        })->sortByDesc('visits')->values();

        // Top chẩn đoán (bệnh chính)
        $topDiagnoses = Diagnosis::whereIn('visit_id', $visits->pluck('id'))
            ->where('is_primary', true)
            ->get(['icd10_code', 'name'])
            ->groupBy('icd10_code')
            ->map(fn (Collection $g, $code) => ['code' => $code, 'name' => $g->first()->name, 'count' => $g->count()])
            ->sortByDesc('count')->take(10)->values();

        $filterLabel = collect([
            $specialtyId ? 'Chuyên khoa: '.($specialties[$specialtyId]->name ?? $specialtyId) : null,
            $doctorId ? 'Bác sĩ: '.($doctors->firstWhere('id', $doctorId)?->display_name ?? $doctorId) : null,
        ])->filter()->implode(' · ');

        return compact('from', 'to', 'kpi', 'daily', 'bySpecialty', 'byItemType', 'doctorStats', 'topDiagnoses', 'filterLabel');
    }

    protected function rate(int $part, int $total): float
    {
        return $total > 0 ? round($part * 100 / $total, 1) : 0.0;
    }

    protected function kpiRows(array $kpi): array
    {
        return [
            ['Lượt khám hoàn thành', $kpi['visits']],
            ['Trong đó vãng lai', $kpi['walk_in']],
            ['Doanh thu thực thu (đ)', $kpi['revenue']],
            ['Doanh thu trung bình / lượt (đ)', $kpi['avg_revenue']],
            ['Số lịch hẹn', $kpi['appointments']],
            ['Tỷ lệ vắng mặt (%)', $kpi['no_show_rate']],
            ['Tỷ lệ hủy lịch (%)', $kpi['cancel_rate']],
            ['Tỷ lệ đặt online (%)', $kpi['online_rate']],
        ];
    }
}
