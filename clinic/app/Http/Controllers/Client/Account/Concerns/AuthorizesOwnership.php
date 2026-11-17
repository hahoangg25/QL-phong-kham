<?php

namespace App\Http\Controllers\Client\Account\Concerns;

use App\Models\Clinical\Patient;

/** BR-09: bệnh nhân chỉ thao tác trên hồ sơ của mình và người thân do mình quản lý. */
trait AuthorizesOwnership
{
    protected function authorizePatient(?Patient $patient): void
    {
        if (! $patient || $patient->user_id !== auth()->id()) {
            abort(403, 'Bạn không có quyền truy cập hồ sơ này.');
        }
    }

    /** Model có quan hệ patient (Appointment, Visit, Invoice...). */
    protected function authorizeOwner(object $model): void
    {
        $this->authorizePatient($model->patient);
    }
}
