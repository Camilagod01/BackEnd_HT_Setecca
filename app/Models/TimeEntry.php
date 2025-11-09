<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'work_date',
        'check_in',
        'check_out',
        'source',
        'notes',
        'status',
        'hours_worked'
    ];

    protected $casts = [
    'work_date' => 'date',
    'check_in'  => 'datetime',
    'check_out' => 'datetime',
];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
