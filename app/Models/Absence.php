<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Absence extends Model
{
    use HasFactory;

    protected $table = 'absences';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'kind',        // 'full_day' | 'hours'
        'hours',       // Si lo necesita la tabla
        'reason',
        'status',      // 'pending' | 'approved' | 'rejected'
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'hours'      => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope para detectar solapamientos de rangos:
     * (startA <= endB) y (endA >= startB)
     */
    public function scopeOverlapping($q, int $employeeId, $start, $end, ?int $ignoreId = null)
    {
        return $q->where('employee_id', $employeeId)
            ->when($ignoreId, fn ($qq) => $qq->where('id', '!=', $ignoreId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start);
    }
}
