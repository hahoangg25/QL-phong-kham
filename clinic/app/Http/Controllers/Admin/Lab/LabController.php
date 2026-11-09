<?php

namespace App\Http\Controllers\Admin\Lab;

use App\Enums\ServiceOrderStatus;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Clinical\LabResult;
use App\Models\Clinical\ServiceOrder;
use App\Notifications\GeneralNotification;
use App\Services\ExaminationService;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Controller Quản lý Cận Lâm Sàng (Chẩn đoán hình ảnh, X-Quang, CT Cone Beam...)
 * 
 * Luồng hoạt động cơ bản gồm 4 bước:
 * 1. index(): Xem danh sách chỉ định chụp phim / xét nghiệm
 * 2. show(): Xem chi tiết chỉ định và thông tin bệnh nhân
 * 3. start(): Kỹ thuật viên bấm bắt đầu tiến hành chụp phim
 * 4. saveResult(): Nhập kết luận, tải ảnh phim X-Quang lên và trả kết quả cho bác sĩ
 */
class LabController extends Controller
{
    /** Các trạng thái của chỉ định cận lâm sàng */
    public const TABS = [
        'pending'     => ServiceOrderStatus::Pending,
        'in_progress' => ServiceOrderStatus::InProgress,
        'completed'   => ServiceOrderStatus::Completed,
    ];

    /**
     * BƯỚC 1: Hiển thị danh sách các ca chỉ định cận lâm sàng.
     * Cho phép lọc theo Ngày, Loại dịch vụ, hoặc Tìm kiếm tên bệnh nhân.
     */
    public function index(Request $request)
    {
        // Xác định tab đang chọn (mặc định là 'pending' - Chờ thực hiện)
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'pending';

        // Lấy ngày cần xem (mặc định là ngày hôm nay)
        try {
            $date = Carbon::parse($request->query('date', today()->toDateString()))->startOfDay();
        } catch (\Throwable) {
            $date = today();
        }
        $type = ServiceType::tryFrom((string) $request->query('type'));
        $term = trim((string) $request->query('q'));

        // Câu truy vấn cơ bản: Lọc theo ngày và tìm kiếm
        $base = ServiceOrder::query()
            ->whereDate('created_at', $date)
            ->when($type, fn ($q) => $q->whereHas('service', fn ($s) => $s->where('type', $type->value)))
            ->when($term !== '', fn ($q) => $q->whereHas('visit.patient', fn ($p) => $p->search($term)));

        // Đếm số lượng theo từng tab để hiển thị badge số lượng
        $counts = collect(self::TABS)->map(fn (ServiceOrderStatus $s) => (clone $base)->where('status', $s->value)->count());

        // Lấy danh sách phân trang cho tab hiện tại
        $orders = (clone $base)
            ->with(['service', 'visit.patient', 'visit.doctor.user', 'visit.room', 'result'])
            ->where('status', self::TABS[$tab]->value)
            ->orderBy($tab === 'completed' ? 'updated_at' : 'created_at', $tab === 'completed' ? 'desc' : 'asc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.lab.index', [
            'orders' => $orders,
            'tab'    => $tab,
            'counts' => $counts,
            'date'   => $date,
            'type'   => $type,
            'term'   => $term,
            'types'  => collect(ServiceType::cases())->filter(fn ($t) => $t->isParaclinical()),
        ]);
    }

    /**
     * BƯỚC 2: Xem chi tiết một chỉ định cận lâm sàng.
     * Hiển thị thông tin người bệnh, ghi chú bác sĩ và form nhập kết quả.
     */
    public function show(ServiceOrder $serviceOrder)
    {
        // Tải các thông tin liên kết cần thiết
        $serviceOrder->load([
            'service', 'orderedBy', 'result.performer',
            'visit.patient', 'visit.doctor.user', 'visit.room', 'visit.diagnoses',
            'visit.serviceOrders.service',
        ]);

        Audit::viewed($serviceOrder->visit, 'KTV cận lâm sàng xem chỉ định', ['service_order_id' => $serviceOrder->id]);

        return view('admin.lab.show', [
            'order' => $serviceOrder,
            'visit' => $serviceOrder->visit,
        ]);
    }

    /**
     * BƯỚC 3: Bắt đầu thực hiện (KTV bấm nút "Bắt đầu").
     * Đổi trạng thái từ "Chờ thực hiện" sang "Đang thực hiện".
     */
    public function start(ServiceOrder $serviceOrder)
    {
        if ($serviceOrder->status !== ServiceOrderStatus::Pending) {
            throw new BusinessRuleException('Chỉ định đang ở trạng thái "'.$serviceOrder->status->label().'".');
        }

        $serviceOrder->update([
            'status'     => ServiceOrderStatus::InProgress,
            'started_at' => now(),
        ]);

        return redirect()->route('staff.lab.show', $serviceOrder)
            ->with('success', 'Đã bắt đầu thực hiện '.$serviceOrder->service->name.'.');
    }

    /**
     * BƯỚC 4: Nhập kết quả, đính kèm file ảnh phim X-Quang / PDF và gửi trả kết quả.
     */
    public function saveResult(Request $request, ServiceOrder $serviceOrder, ExaminationService $exam)
    {
        $serviceOrder->load(['visit.patient.user', 'service', 'orderedBy', 'result']);

        // Kiểm tra hợp lệ: Chỉ định không bị hủy và bệnh án chưa bị khóa
        if ($serviceOrder->status === ServiceOrderStatus::Cancelled) {
            throw new BusinessRuleException('Chỉ định đã bị hủy, không thể nhập kết quả.');
        }
        if ($serviceOrder->visit->isLocked()) {
            throw new BusinessRuleException('Bệnh án đã hoàn tất và bị khóa, không thể sửa kết quả.');
        }

        // Kiểm tra dữ liệu đầu vào
        $data = $request->validate([
            'result'     => ['required', 'string', 'max:10000'],
            'conclusion' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'], // File ảnh hoặc PDF <= 5MB
        ]);

        $wasCompleted = $serviceOrder->status === ServiceOrderStatus::Completed;
        $oldPath      = $serviceOrder->result?->attachment_path;
        $file         = $request->file('attachment');

        // Lưu thông tin kết quả và cập nhật trạng thái
        DB::transaction(function () use ($serviceOrder, $data, $file, $request) {
            $values = [
                'performed_by' => $request->user()->id,
                'result'       => $data['result'],
                'conclusion'   => $data['conclusion'] ?? null,
                'completed_at' => now(),
            ];

            // Nếu có upload ảnh phim mới
            if ($file) {
                $values['attachment_path'] = $file->store('lab-results', 'local');
                $values['attachment_name'] = $file->getClientOriginalName();
            }

            // Lưu vào bảng lab_results
            LabResult::updateOrCreate(['service_order_id' => $serviceOrder->id], $values);

            // Cập nhật trạng thái chỉ định sang "Hoàn thành"
            $serviceOrder->update([
                'status'     => ServiceOrderStatus::Completed,
                'started_at' => $serviceOrder->started_at ?? now(),
            ]);
        });

        // Xóa tệp cũ nếu đã tải lên tệp mới
        if ($file && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        // Tự động chuyển ca khám sang "Đang khám" nếu đã có đủ kết quả cận lâm sàng
        $visit = $serviceOrder->visit;
        $exam->resumeIfResultsReady($visit, $request->user());

        // Thông báo cho Bác sĩ điều trị và Bệnh nhân
        $title   = $wasCompleted ? 'Kết quả cận lâm sàng được cập nhật' : 'Đã có kết quả cận lâm sàng';
        $message = $serviceOrder->service->name.' - '.$visit->patient->full_name;

        rescue(fn () => $serviceOrder->orderedBy?->notify(new GeneralNotification(
            $title, $message, route('staff.exam.show', [$visit, 'tab' => 'orders']), 'bi-clipboard2-check'
        )));

        if ($owner = $visit->patient->user) {
            rescue(fn () => $owner->notify(new GeneralNotification(
                $title, $message, route('account.visits.show', $visit), 'bi-clipboard2-check'
            )));
        }

        return redirect()->route('staff.lab.index', ['tab' => 'completed', 'date' => $serviceOrder->created_at->toDateString()])
            ->with('success', ($wasCompleted ? 'Đã cập nhật' : 'Đã lưu và trả').' kết quả '.$serviceOrder->service->name.' cho bác sĩ chỉ định.');
    }
}
