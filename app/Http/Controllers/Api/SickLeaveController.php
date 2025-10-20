<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\SickLeave;
use Carbon\Carbon;

class SickLeaveController extends Controller
{
    /**
     * GET /api/sick-leaves?employee_id=&status=&provider=&from=&to=
     * Lista paginada y filtrada.
     */
    public function index(Request $request)
    {
        try {
            $q = SickLeave::query()->with('employee:id,first_name,last_name,code');

            if ($emp = $request->get('employee_id')) {
                $q->where('employee_id', $emp);
            }
            if ($st = $request->get('status')) {
                $q->where('status', $st);
            }
            if ($prov = $request->get('provider')) {
                $q->where('provider', $prov);
            }
            if ($from = $request->get('from')) {
                $q->whereDate('end_date', '>=', $from);
            }
            if ($to = $request->get('to')) {
                $q->whereDate('start_date', '<=', $to);
            }

            $perPage = (int) $request->get('per_page', 15);
            if ($perPage <= 0 || $perPage > 100) $perPage = 15;

            return response()->json(
                $q->orderByDesc('start_date')->paginate($perPage)
            );
        } catch (\Throwable $e) {
            Log::error("Error en SickLeaveController@index: " . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * POST /api/sick-leaves
     * Crea una nueva incapacidad.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'       => ['required', 'exists:employees,id'],
            'start_date'        => ['required', 'date'],
            'end_date'          => ['required', 'date', 'after_or_equal:start_date'],
            'provider'          => ['required', Rule::in(['CCSS', 'INS', 'OTHER'])],
            'coverage_percent'  => ['required', 'numeric', 'min:0', 'max:100'],
            'status'            => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ]);

        // Calcular días totales
        $start = Carbon::parse($data['start_date']);
        $end   = Carbon::parse($data['end_date']);
        $data['total_days'] = $start->diffInDays($end) + 1;

        // Validar superposición
        $overlap = SickLeave::overlapping(
            $data['employee_id'],
            $data['start_date'],
            $data['end_date']
        )->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Ya existe una incapacidad que se superpone con el rango indicado.'
            ], 422);
        }

        $leave = SickLeave::create($data);

        return response()->json($leave->fresh()->load('employee:id,first_name,last_name,code'), 201);
    }

    /**
     * PATCH /api/sick-leaves/{id}
     * Actualiza una incapacidad.
     */
    public function update(Request $request, $id)
    {
        $leave = SickLeave::findOrFail($id);

        $data = $request->validate([
            'start_date'        => ['sometimes', 'date'],
            'end_date'          => ['sometimes', 'date', 'after_or_equal:start_date'],
            'provider'          => ['sometimes', Rule::in(['CCSS', 'INS', 'OTHER'])],
            'coverage_percent'  => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'status'            => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ]);

        // Recalcular total_days si cambian fechas
        $start = $data['start_date'] ?? $leave->start_date->format('Y-m-d');
        $end   = $data['end_date'] ?? $leave->end_date->format('Y-m-d');
        $data['total_days'] = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;

        // Validar solapamiento
        $overlap = SickLeave::overlapping($leave->employee_id, $start, $end, $leave->id)->exists();
        if ($overlap) {
            return response()->json([
                'message' => 'Ya existe otra incapacidad que se superpone con el rango indicado.'
            ], 422);
        }

        $leave->update($data);
        return response()->json($leave->fresh());
    }

    /**
     * DELETE /api/sick-leaves/{id}
     * Elimina una incapacidad.
     */
    public function destroy($id)
    {
        $leave = SickLeave::findOrFail($id);
        $leave->delete();

        return response()->json(['status' => 'ok', 'message' => 'Incapacidad eliminada']);
    }
}
