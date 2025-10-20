<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Advance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'amount',
        'currency',        // ENUM: CRC|USD
        'granted_at',      // DATE NOT NULL
        'notes',
        'status',          // ENUM: pending|applied|cancelled
        'scheduling_json', // JSON NULL
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'granted_at'      => 'date',
        'scheduling_json' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
