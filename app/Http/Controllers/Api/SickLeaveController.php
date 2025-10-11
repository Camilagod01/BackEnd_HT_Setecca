<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SickLeave;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SickLeaveController extends Controller
{

    use HasFactory;

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'type',      // '50pct' | '0pct'
        'status',    // 'draft' | 'approved' | 'rejected' (si lo usas)
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope para buscar rangos que se superponen.
     *
     * @param \Illuminate\Database\Eloquent\Builder $q
     * @param int $employeeId
     * @param string|\DateTimeInterface $start Y-m-d
     * @param string|\DateTimeInterface $end   Y-m-d
     * @param int|null $excludeId  (opcional) id a excluir (útil en update)
    */

    public function scopeOverlapping($q, int $employeeId, $start, $end, ?int $excludeId = null)
    {
        $q->where('employee_id', $employeeId)
          // solapa si (startA <= endB) y (endA >= startB)
          ->whereDate('start_date', '<=', $end)
          ->whereDate('end_date', '>=', $start);

        if ($excludeId) {
            $q->where('id', '!=', $excludeId);
        }

        return $q;
    }

    /**
     * GET /api/sick-leaves?employee_id=&from=&to=
     * Lista paginada y filtrada.
     */
    public function index(Request $request)
    {
        try {
            $q = SickLeave::query()->with('employee:id,first_name,last_name,code');

            if ($emp = $request->get('employee_id')) {
                $q->where('employee_id', $emp);
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
            Log::error("Error en SickLeaveController@index: ".$e->getMessage());
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
            'employee_id' => ['required', 'exists:employees,id'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'],
            'type'        => ['required', Rule::in(['50pct', '0pct'])],
            'notes'       => ['nullable', 'string'],
        ]);

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

        return response()->json($leave, 201);
    }

    /**
     * PATCH /api/sick-leaves/{id}
     * Actualiza una incapacidad.
     */
    public function update(Request $request, $id)
    {
        $leave = SickLeave::findOrFail($id);

        $data = $request->validate([
            'start_date'  => ['sometimes', 'date'],
            'end_date'    => ['sometimes', 'date', 'after_or_equal:start_date'],
            'type'        => ['sometimes', Rule::in(['50pct', '0pct'])],
            'notes'       => ['nullable', 'string'],
        ]);

        // Validar superposición si cambia fechas
        $empId = $leave->employee_id;
        $start = $data['start_date'] ?? $leave->start_date->format('Y-m-d');
        $end   = $data['end_date'] ?? $leave->end_date->format('Y-m-d');

        $overlap = SickLeave::overlapping($empId, $start, $end, $leave->id)->exists();
        if ($overlap) {
            return response()->json([
                'message' => 'Ya existe otra incapacidad que se superpone con el rango indicado.'
            ], 422);
        }

        $leave->update($data);
        return response()->json($leave);
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
