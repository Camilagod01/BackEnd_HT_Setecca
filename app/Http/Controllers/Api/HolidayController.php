<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Holiday\StoreHolidayRequest;
use App\Http\Requests\Holiday\UpdateHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HolidayController extends Controller
{
    // Sin authorizeResource y sin middleware "can:*"

    /**
     * GET /api/holidays?year=&month=&from=&to=&scope=&paid=&per_page=
     */
    public function index(Request $request)
    {
        try {
            $q = Holiday::query()
                ->when($request->filled('scope'), fn($qq) => $qq->where('scope', $request->scope))
                ->when($request->filled('paid'), function ($qq) use ($request) {
                    // paid puede venir como "1","0","true","false"
                    $val = filter_var($request->paid, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    if (!is_null($val)) {
                        $qq->where('paid', $val);
                    }
                });

            // Año y mes rápidos
            if ($request->filled('year')) {
                $q->whereYear('date', (int)$request->year);
            }
            if ($request->filled('month')) {
                $q->whereMonth('date', (int)$request->month);
            }

            // Rango de fechas
            if ($request->filled('from')) {
                $q->whereDate('date', '>=', $request->from);
            }
            if ($request->filled('to')) {
                $q->whereDate('date', '<=', $request->to);
            }

            $q->orderBy('date');

            $perPage = (int) ($request->get('per_page', 50));
            return $perPage > 0 ? $q->paginate($perPage) : $q->get();
        } catch (\Throwable $e) {
            Log::error('Holiday@index: '.$e->getMessage());
            return response()->json(['message' => 'Error interno'], 500);
        }
    }

    /**
     * POST /api/holidays
     */
    public function store(StoreHolidayRequest $request)
    {
        $row = Holiday::create($request->validated());
        return response()->json($row, 201);
    }

    /**
     * GET /api/holidays/{holiday}
     */
    public function show(Holiday $holiday)
    {
        return $holiday;
    }

    /**
     * PATCH /api/holidays/{holiday}
     */
    public function update(UpdateHolidayRequest $request, Holiday $holiday)
    {
        $holiday->fill($request->validated())->save();
        return $holiday->fresh();
    }

    /**
     * DELETE /api/holidays/{holiday}
     */
    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return response()->json([], 204);
    }
}
