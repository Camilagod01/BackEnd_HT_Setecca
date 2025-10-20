<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Justification\StoreJustificationRequest;
use App\Http\Requests\Justification\UpdateJustificationRequest;
use App\Models\Justification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JustificationController extends Controller
{
    /**
     * GET /api/justifications?employee_id=&from=&to=&type=&status=&per_page=
     */
    public function index(Request $request)
    {
        try {
            $q = Justification::query()
                ->with('employee:id,code,first_name,last_name');

            if ($emp = $request->get('employee_id')) {
                $q->where('employee_id', $emp);
            }

            if ($from = $request->get('from')) {
                $q->whereDate('date', '>=', $from);
            }

            if ($to = $request->get('to')) {
                $q->whereDate('date', '<=', $to);
            }

            if ($type = $request->get('type')) {
                $q->where('type', $type);
            }

            if ($status = $request->get('status')) {
                $q->where('status', $status);
            }

            $q->orderByDesc('date')->orderBy('employee_id');

            $perPage = (int) $request->get('per_page', 15);
            if ($perPage > 0) {
                return $q->paginate($perPage);
            }
            return $q->get();
        } catch (\Throwable $e) {
            Log::error('Justification@index: '.$e->getMessage());
            return response()->json(['message' => 'Error interno'], 500);
        }
    }

    /**
     * POST /api/justifications
     */
    public function store(StoreJustificationRequest $request)
    {
        $data = $request->validated();

        // Validación de solapamiento si viene rango horario
        $from = $data['from_time'] ?? null;
        $to   = $data['to_time']   ?? null;
        if ($from && $to) {
            $exists = Justification::overlapping(
                (int) $data['employee_id'],
                $data['date'],
                $from,
                $to
            )->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe una justificación que se superpone con el rango indicado.'
                ], 422);
            }
        }

        $row = Justification::create($data);
        return response()->json($row->load('employee:id,code,first_name,last_name'), 201);
    }

    /**
     * GET /api/justifications/{justification}
     */
    public function show(Justification $justification)
    {
        return $justification->load('employee:id,code,first_name,last_name');
    }

    /**
     * PATCH /api/justifications/{justification}
     * - Permite actualizar campos y también status (pending|approved|rejected)
     */
    public function update(UpdateJustificationRequest $request, Justification $justification)
    {
        $data = $request->validated();

        // Validación de solapamiento si cambian horas
        $empId = (int) ($data['employee_id'] ?? $justification->employee_id);
        $date  = $data['date'] ?? $justification->date->format('Y-m-d');
        $from  = $data['from_time'] ?? $justification->from_time?->format('H:i');
        $to    = $data['to_time']   ?? $justification->to_time?->format('H:i');

        if ($from && $to) {
            $exists = Justification::overlapping($empId, $date, $from, $to)
                ->where('id', '!=', $justification->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe otra justificación que se superpone con el rango indicado.'
                ], 422);
            }
        }

        $justification->fill($data)->save();
        return $justification->fresh()->load('employee:id,code,first_name,last_name');
    }

    /**
     * PATCH /api/justifications/{justification}/status
     * Body: { "status": "approved" | "rejected" | "pending" }
     */
    public function updateStatus(Request $request, Justification $justification)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $justification->status = $validated['status'];
        $justification->save();

        return $justification->fresh()->load('employee:id,code,first_name,last_name');
    }

    /**
     * DELETE /api/justifications/{justification}
     */
    public function destroy(Justification $justification)
    {
        $justification->delete();
        return response()->json([], 204);
    }
}
