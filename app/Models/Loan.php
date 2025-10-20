<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'amount',
        'principal',
        'currency',     // 'CRC' | 'USD'
        'granted_at',   // DATE
        'status',       // 'active' | 'closed'
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'granted_at' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }
}
