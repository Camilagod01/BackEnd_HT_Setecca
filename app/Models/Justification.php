<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Justification extends Model
{
    use HasFactory;

    protected $table = 'justifications';

    protected $fillable = [
        'employee_id',
        'date',
        'from_time',   // Si lo necesita la tabla
        'to_time',     // Si lo necesita la tabla
        'type',        // 'late' | 'early_leave' | 'absence' | 'other'
        'reason',
        'notes',
        'status',      // 'pending' | 'approved' | 'rejected'
    ];

    protected $casts = [
        'date'      => 'date:Y-m-d',
        'from_time' => 'datetime:H:i',
        'to_time'   => 'datetime:H:i',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope para filtrar por solapamiento horario en una fecha.
     * Útil si usas rangos (from_time/to_time) para justificar horas.
     */
    public function scopeOverlapping($q, int $employeeId, string $date, ?string $fromTime, ?string $toTime)
    {
        $q->where('employee_id', $employeeId)
          ->whereDate('date', $date);

        if ($fromTime && $toTime) {
            // Solapa si (A.from <= B.to) y (A.to >= B.from)
            $q->where(function ($qq) use ($fromTime, $toTime) {
                $qq->where(function ($qqq) use ($fromTime, $toTime) {
                    $qqq->where('from_time', '<=', $toTime)
                        ->where('to_time', '>=', $fromTime);
                })
                // Si lo necesita la tabla: manejar casos con nulos
                ->orWhereNull('from_time')
                ->orWhereNull('to_time');
            });
        }

        return $q;
    }
}
