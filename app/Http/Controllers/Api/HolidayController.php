<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Holiday\StoreHolidayRequest;
use App\Http\Requests\Holiday\UpdateHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class HolidayController extends Controller
{
    // Sin authorizeResource y sin middleware "can:*"

    /**
     * GET /api/holidays?year=&month=&from=&to=&scope=&origin=&paid=&per_page=
     */
    public function index(Request $request)
    {
        try {
            // Normaliza parámetros
            $perPage = (int) $request->integer('per_page', 50);
            if ($perPage < 0) { $perPage = 50; }

            $year  = $request->integer('year');   // null si no viene
            $month = $request->integer('month');  // null si no viene

            // --- AUTOGENERACIÓN SI NO EXISTEN FERIADOS PARA ESE AÑO ---
            if ($year && $year >= 1900 && $year <= 2100) {
                $exists = Holiday::whereYear('date', $year)
                    ->where('origin', 'default')
                    ->exists();

                if (!$exists) {
                    try {
                        // No pasamos --include-sundays porque tu comando no la define
                        Artisan::call('holidays:generate-default', [
                            'year' => $year,
                            // '--reset' => '0', // descomenta si tu comando define esta opción
                        ]);
                        Log::info("[Holidays] Generados automáticamente al consultar el año {$year}");
                    } catch (\Throwable $ex) {
                        Log::error("Autogeneración feriados {$year} falló: {$ex->getMessage()} @{$ex->getFile()}:{$ex->getLine()}");
                        // No bloqueamos la respuesta si falla la autogeneración
                    }
                }
            }

            // --- CONSTRUCCIÓN DE CONSULTA ---
            $q = Holiday::query()
                // scope: si no viene, mostramos national + company
                ->when(
                    $request->filled('scope'),
                    fn ($qq) => $qq->where('scope', $request->scope),
                    fn ($qq) => $qq->whereIn('scope', ['national', 'company'])
                )
                // origin: si no viene, mostramos manual + default
                ->when(
                    $request->filled('origin') && in_array($request->origin, ['manual', 'default'], true),
                    fn ($qq) => $qq->where('origin', $request->origin),
                    fn ($qq) => $qq->whereIn('origin', ['manual', 'default'])
                )
                // paid: "1","0","true","false"
                ->when($request->filled('paid'), function ($qq) use ($request) {
                    $val = filter_var($request->paid, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    if (!is_null($val)) {
                        $qq->where('paid', $val);
                    }
                });

            // Año / mes / rango
            if ($year) {
                $q->whereYear('date', (int) $year);
            }
            if ($month && $month >= 1 && $month <= 12) {
                $q->whereMonth('date', (int) $month);
            }
            if ($request->filled('from')) {
                $q->whereDate('date', '>=', $request->from);
            }
            if ($request->filled('to')) {
                $q->whereDate('date', '<=', $request->to);
            }

            $q->orderBy('date');

            return $perPage > 0 ? $q->paginate($perPage) : $q->get();
        } catch (\Throwable $e) {
            Log::error('Holiday@index: '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
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
