<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holidays';

    protected $fillable = [
        'date',
        'name',
        'scope',  // 'national' | 'company'
        'paid',   // Si lo necesita la tabla
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'paid' => 'boolean',
    ];
}
