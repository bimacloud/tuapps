<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an action to the audit logs.
     *
     * @param string $action (e.g., 'CREATE', 'UPDATE', 'DELETE', 'LOGIN')
     * @param string $module (e.g., 'PAYMENT', 'CASHIER_SESSION', 'AUTH')
     * @param string $description Human readable description
     * @param mixed $model The Eloquent model involved (optional)
     * @param array $oldData (optional)
     * @param array $newData (optional)
     * @return AuditLog
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        $model = null,
        array $oldData = null,
        array $newData = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'old_data' => $oldData,
            'new_data' => $newData,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
