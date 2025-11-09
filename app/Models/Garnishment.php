<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Garnishment extends Model
{
    use HasFactory;

    // Alineado a schema actual:
    protected $fillable = [
        'employee_id',
        'order_no',     // nullable
        'mode',         // 'percent' | 'amount'
        'value',        // decimal(8,2)
        'start_date',   // date
        'end_date',     // date|null
        'priority',     // int default 1
        'active',       // bool (tinyint 1/0)
        'notes',        // text|null
    ];

    protected $casts = [
        'value'      => 'decimal:2',
        'start_date' => 'date',
        'end_date'   => 'date',
        'priority'   => 'integer',
        'active'     => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
