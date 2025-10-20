<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\StoreAbsenceRequest;
use App\Http\Requests\Absence\UpdateAbsenceRequest;
use App\Models\Absence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AbsenceController extends Controller
{
    // Sin authorizeResource y sin middleware "can:*"

    /**
     * GET /api/absences?employee_id=&kind=&status=&from=&to=&per_page=
     */
    public function index(Request $request)
    {
        try {
            $q = Absence::query()
                ->with('employee:id,code,first_name,last_name')
                ->when($request->filled('employee_id'), fn ($qq) => $qq->where('employee_id', (int)$request->employee_id))
                ->when($request->filled('kind'),        fn ($qq) => $qq->where('kind',  $request->kind))
                ->when($request->filled('status'),      fn ($qq) => $qq->where('status',$request->status))
                ->when($request->filled('from'),        fn ($qq) => $qq->whereDate('end_date', '>=', $request->from))
                ->when($request->filled('to'),          fn ($qq) => $qq->whereDate('start_date', '<=', $request->to))
                ->orderByDesc('start_date');

            $perPage = (int) ($request->get('per_page', 10));
            return $perPage > 0 ? $q->paginate($perPage) : $q->get();
        } catch (\Throwable $e) {
            Log::error('Absence@index: '.$e->getMessage());
            return response()->json(['message' => 'Error interno'], 500);
        }
    }

    /**
     * POST /api/absences
     */
    public function store(StoreAbsenceRequest $request)
    {
        $row = Absence::create($request->validated());
        return response()->json($row->loadMissing('employee:id,code,first_name,last_name'), 201);
    }

    /**
     * GET /api/absences/{absence}
     */
    public function show(Absence $absence)
    {
        return $absence->loadMissing('employee:id,code,first_name,last_name');
    }

    /**
     * PATCH /api/absences/{absence}
     * (Permite también cambiar status: pending/approved/rejected)
     */
    public function update(UpdateAbsenceRequest $request, Absence $absence)
    {
        $absence->fill($request->validated())->save();
        return $absence->fresh()->loadMissing('employee:id,code,first_name,last_name');
    }

    /**
     * DELETE /api/absences/{absence}
     */
    public function destroy(Absence $absence)
    {
        $absence->delete();
        return response()->json([], 204);
    }
}

