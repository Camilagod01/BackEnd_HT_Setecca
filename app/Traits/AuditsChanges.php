<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait AuditsChanges
{
    /**
     * Registra un evento de auditoría.
     *
     * @param Model  $model   Modelo afectado
     * @param array  $before  Estado anterior (array)
     * @param array  $after   Estado posterior (array)
     * @param string $action  created|patched|deleted|position_changed|...
     */
    protected function audit(Model $model, array $before, array $after, string $action): void
    {
        try {
            AuditLog::create([
                'table_name'    => $model->getTable(),
                'record_id'     => $model->getKey(),
                'user_id'       => Auth::id(),
                'action'        => $action,
                'before_values' => $before,
                'after_values'  => $after,
                'ip'            => request()->ip(),
                'user_agent'    => request()->header('User-Agent'),
            ]);
        } catch (\Throwable $e) {
            
            Log::warning('audit_failed', [
                'action' => $action,
                'table'  => $model->getTable(),
                'id'     => $model->getKey(),
                'err'    => $e->getMessage(),
            ]);
        }
    }
}
