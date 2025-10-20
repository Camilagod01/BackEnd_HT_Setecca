<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SickLeave extends Model
{
    use HasFactory;

    protected $table = 'sick_leaves';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'total_days',
        'provider',           // 'CCSS' | 'INS' | 'OTHER'
        'coverage_percent',   // 0–100
        'status',             // 'pending' | 'approved' | 'rejected'
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date'       => 'date:Y-m-d',
        'end_date'         => 'date:Y-m-d',
        'total_days'       => 'integer',
        'coverage_percent' => 'decimal:2',
    ];

    // Relaciones
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Scope para validar solapamientos de rangos (conservado)
    public function scopeOverlapping($q, $employeeId, $start, $end, $ignoreId = null)
    {
        return $q->where('employee_id', $employeeId)
            ->when($ignoreId, fn ($qq) => $qq->where('id', '!=', $ignoreId))
            ->where(function ($qq) use ($start, $end) {
                $qq->whereBetween('start_date', [$start, $end])
                   ->orWhereBetween('end_date', [$start, $end])
                   ->orWhere(function ($qqq) use ($start, $end) {
                       $qqq->where('start_date', '<=', $start)
                           ->where('end_date', '>=', $end);
                   });
            });
    }
}
