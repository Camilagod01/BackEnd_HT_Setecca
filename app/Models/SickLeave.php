<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SickLeave extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'start_date', 'end_date', 'type', 'status', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // scope para validar solapamientos de rangos
    public function scopeOverlapping($q, $employeeId, $start, $end, $ignoreId = null)
    {
        return $q->where('employee_id', $employeeId)
            ->when($ignoreId, fn($qq) => $qq->where('id', '!=', $ignoreId))
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
