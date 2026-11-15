<?php

namespace App\Http\Controllers\Admin\Management;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/** QT-17: tra cứu nhật ký hệ thống (audit log) theo người dùng, đối tượng, thời gian. */
class AuditLogController extends Controller
{
    public const LOG_LABELS = [
        'medical_record' => 'Hồ sơ y tế',
        'appointment' => 'Lịch hẹn',
        'patient' => 'Bệnh nhân',
        'billing' => 'Thu ngân',
        'pharmacy' => 'Dược - kho',
        'catalog' => 'Danh mục',
        'schedule' => 'Lịch làm việc',
        'account' => 'Tài khoản',
        'auth' => 'Đăng nhập',
        'settings' => 'Cấu hình',
        'default' => 'Khác',
    ];

    public const EVENT_LABELS = [
        'created' => ['Tạo mới', 'success'],
        'updated' => ['Cập nhật', 'primary'],
        'deleted' => ['Xóa', 'danger'],
        'viewed' => ['Xem', 'info'],
    ];

    public function index(Request $request)
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $causerIds = null;
        if ($request->filled('user')) {
            $term = $request->input('user');
            $causerIds = User::where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->pluck('id');
        }

        $allTypes = Activity::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type');
        $subjectTypes = $request->filled('subject_type')
            ? $allTypes->filter(fn ($t) => class_basename($t) === $request->input('subject_type'))->values()
            : null;

        $logs = Activity::with('causer')
            ->when($request->filled('log_name'), fn ($q) => $q->where('log_name', $request->log_name))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->event))
            ->when($subjectTypes !== null, fn ($q) => $q->whereIn('subject_type', $subjectTypes->all() ?: ['-']))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($causerIds !== null, fn ($q) => $q->whereIn('causer_id', $causerIds)->where('causer_type', User::class))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->from)->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->to)->endOfDay()))
            ->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.management.audit.index', [
            'logs' => $logs,
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
            'subjectTypes' => $allTypes->map(fn ($t) => class_basename($t))->unique()->sort()->values(),
            'logLabels' => self::LOG_LABELS,
            'eventLabels' => self::EVENT_LABELS,
        ]);
    }
}
