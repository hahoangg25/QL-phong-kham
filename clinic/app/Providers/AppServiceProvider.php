<?php

namespace App\Providers;

use App\Enums\Role;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        // Giữ tên lớp cũ (App\Models\X) trong dữ liệu đã lưu như activity_log sau khi chia model theo nhóm
        Relation::morphMap([
            'App\\Models\\Specialty' => \App\Models\Catalog\Specialty::class,
            'App\\Models\\Doctor' => \App\Models\Catalog\Doctor::class,
            'App\\Models\\Room' => \App\Models\Catalog\Room::class,
            'App\\Models\\Service' => \App\Models\Catalog\Service::class,
            'App\\Models\\Icd10Code' => \App\Models\Catalog\Icd10Code::class,
            'App\\Models\\WorkShift' => \App\Models\Scheduling\WorkShift::class,
            'App\\Models\\LeaveRequest' => \App\Models\Scheduling\LeaveRequest::class,
            'App\\Models\\TimeSlot' => \App\Models\Scheduling\TimeSlot::class,
            'App\\Models\\SlotHold' => \App\Models\Scheduling\SlotHold::class,
            'App\\Models\\Appointment' => \App\Models\Scheduling\Appointment::class,
            'App\\Models\\AppointmentStatusLog' => \App\Models\Scheduling\AppointmentStatusLog::class,
            'App\\Models\\Patient' => \App\Models\Clinical\Patient::class,
            'App\\Models\\Visit' => \App\Models\Clinical\Visit::class,
            'App\\Models\\VisitAddendum' => \App\Models\Clinical\VisitAddendum::class,
            'App\\Models\\Diagnosis' => \App\Models\Clinical\Diagnosis::class,
            'App\\Models\\ServiceOrder' => \App\Models\Clinical\ServiceOrder::class,
            'App\\Models\\LabResult' => \App\Models\Clinical\LabResult::class,
            'App\\Models\\Prescription' => \App\Models\Clinical\Prescription::class,
            'App\\Models\\PrescriptionItem' => \App\Models\Clinical\PrescriptionItem::class,
            'App\\Models\\Review' => \App\Models\Clinical\Review::class,
            'App\\Models\\Medicine' => \App\Models\Pharmacy\Medicine::class,
            'App\\Models\\MedicineBatch' => \App\Models\Pharmacy\MedicineBatch::class,
            'App\\Models\\StockReceipt' => \App\Models\Pharmacy\StockReceipt::class,
            'App\\Models\\StockTransaction' => \App\Models\Pharmacy\StockTransaction::class,
            'App\\Models\\Invoice' => \App\Models\Billing\Invoice::class,
            'App\\Models\\InvoiceItem' => \App\Models\Billing\InvoiceItem::class,
            'App\\Models\\Payment' => \App\Models\Billing\Payment::class,
        ]);

        Carbon::setLocale('vi');
        Paginator::useBootstrapFive();

        // Quản trị viên có toàn quyền (RBAC - QT-02)
        Gate::before(fn ($user) => $user->hasRole(Role::Admin->value) ? true : null);

        View::composer(['admin.layouts.app', 'client.layouts.site', 'client.layouts.account'], function ($view) {
            $user = auth()->user();
            $view->with('unreadNotifications', $user ? $user->unreadNotifications()->count() : 0);
        });
    }
}
