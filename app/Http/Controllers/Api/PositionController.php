<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;

class PositionController extends Controller
{
    public function index()
    {
        return response()->json(
            Position::select('id', 'code', 'name', 'base_hourly_rate', 'currency')
                ->orderBy('name')
                ->get()
        );
    }
}
