<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;  
use Illuminate\Support\Facades\Request;

trait AuditsChanges
{
    protected function audit(Model $model, array $before, array $after, string $action): void
    {
        AuditLog::create([
            'table_name'   => $model->getTable(),
            'record_id'    => $model->getKey(),
            'user_id'      => Auth::id()(),
            'action'       => $action,
            'before_values'=> $before,
            'after_values' => $after,
            'ip'           => Request::ip(),
            'user_agent'   => Request::header('User-Agent'),
        ]);
    }
}

