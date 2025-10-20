<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vacation\StoreVacationRequest;
use App\Http\Requests\Vacation\UpdateVacationRequest;
use App\Models\Vacation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VacationController extends Controller
{
    // No apliquemos __construct con authorizeResource ni middleware "can:*"

    /** GET /api/vacations */
    public function index(Request $request)
    {
        try {
            $q = Vacation::query()
                ->with('employee:id,code,first_name,last_name')
                ->when($request->filled('employee_id'), fn ($qq) => $qq->where('employee_id', (int)$request->employee_id))
                ->when($request->filled('status'), fn ($qq) => $qq->where('status', $request->status))
                ->when($request->filled('from'), fn ($qq) => $qq->whereDate('end_date', '>=', $request->from))
                ->when($request->filled('to'), fn ($qq) => $qq->whereDate('start_date', '<=', $request->to))
                ->orderByDesc('start_date');

            $perPage = (int)($request->get('per_page', 10));
            return $perPage > 0 ? $q->paginate($perPage) : $q->get();
        } catch (\Throwable $e) {
            Log::error("Vacation@index: ".$e->getMessage());
            return response()->json(['message' => 'Error interno'], 500);
        }
    }

    /** POST /api/vacations */
    public function store(StoreVacationRequest $request)
    {
        $row = Vacation::create($request->validated());
        return response()->json($row->loadMissing('employee:id,code,first_name,last_name'), 201);
    }

    /** GET /api/vacations/{vacation} */
    public function show(Vacation $vacation)
    {
        return $vacation->loadMissing('employee:id,code,first_name,last_name');
    }

    /** PATCH /api/vacations/{vacation} */
    public function update(UpdateVacationRequest $request, Vacation $vacation)
    {
        $vacation->fill($request->validated())->save();
        return $vacation->fresh()->loadMissing('employee:id,code,first_name,last_name');
    }

    /** DELETE /api/vacations/{vacation} */
    public function destroy(Vacation $vacation)
    {
        $vacation->delete();
        return response()->json([], 204);
    }
}
