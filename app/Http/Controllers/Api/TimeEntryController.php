<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use App\Traits\AuditsChanges;
use Carbon\Carbon;

class TimeEntryController extends Controller
{
    use AuditsChanges; // comenta si aún no tienes el trait

    /**
     * GET /api/time-entries
     * Filtros:
     * - q: busca por code o "nombre apellido"
     * - employee_id: exacto
     * - date: YYYY-MM-DD (compat)
     * - from, to: rango YYYY-MM-DD
     * - status: completo | pendiente_salida | anomalia
     * - per_page: paginación (1..100)
     */
    public function index(Request $request)
    {
        $q = TimeEntry::with(['employee:id,code,first_name,last_name'])
            ->orderByDesc('work_date')
            ->orderByDesc('id');

        // Fecha exacta (compat)
        if ($date = $request->get('date')) {
            $q->whereDate('work_date', $date);
        }

        // Filtro por empleado
        if ($emp = $request->get('employee_id')) {
            $q->where('employee_id', $emp);
        }

        // Búsqueda por empleado (code o nombre completo)
        if ($search = trim((string)$request->get('q', ''))) {
            $q->whereHas('employee', function ($w) use ($search) {
                $w->where('code', 'like', "%{$search}%")
                  ->orWhereRaw("concat(first_name,' ',last_name) like ?", ["%{$search}%"]);
            });
        }

        // Rango de fechas
        $from = $request->get('from');
        $to   = $request->get('to');
        if ($from && $to) {
            $q->whereBetween('work_date', [$from, $to]);
        } elseif ($from) {
            $q->whereDate('work_date', '>=', $from);
        } elseif ($to) {
            $q->whereDate('work_date', '<=', $to);
        }

        // Estado calculado
        if ($status = $request->get('status')) {
            $q->where(function ($w) use ($status) {
                if ($status === 'completo') {
                    $w->whereNotNull('check_in')
                      ->whereNotNull('check_out')
                      ->whereColumn('check_out', '>', 'check_in');
                } elseif ($status === 'pendiente_salida') {
                    $w->whereNotNull('check_in')
                      ->whereNull('check_out');
                } elseif ($status === 'anomalia') {
                    $w->whereNull('check_in')
                      ->orWhere(function ($x) {
                          $x->whereNotNull('check_in')
                            ->whereNotNull('check_out')
                            ->whereColumn('check_out', '<', 'check_in');
                      });
                }
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0 || $perPage > 100) $perPage = 20;

        $paginator = $q->paginate($perPage);

        // Agregar status calculado a cada item (solo salida)
        $paginator->setCollection(
            $paginator->getCollection()->map(function ($row) {
                $row->status = $this->computeStatus($row->check_in, $row->check_out);
                return $row;
            })
        );

        return response()->json($paginator);
    }

    // POST /api/time-entries
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required','exists:employees,id'],
            'work_date'   => ['required','date'],
            'check_in'    => ['required','date'], // aceptamos varios formatos, normalizamos abajo
            'check_out'   => ['nullable','date','after:check_in'],
            'source'      => ['required','in:portal,forms_csv'],
            'notes'       => ['nullable','string'],
        ]);

        // Normaliza formatos
        $data['work_date'] = Carbon::parse($data['work_date'])->toDateString();
        $data['check_in']  = Carbon::parse($data['check_in'])->format('Y-m-d H:i:s');
        if (!empty($data['check_out'])) {
            $data['check_out'] = Carbon::parse($data['check_out'])->format('Y-m-d H:i:s');
        }

        $entry = TimeEntry::create($data);

        if (!empty($entry->check_out)) {
            $entry->hours_worked = $this->calcHours($entry->check_in, $entry->check_out);
            $entry->save();
        }

        // Status calculado en respuesta
        $entry->status = $this->computeStatus($entry->check_in, $entry->check_out);

        return response()->json($entry->load('employee'), 201);
    }

    // PATCH /api/time-entries/{id}
    public function update($id, Request $request)
    {
        $entry = TimeEntry::with('employee:id,code,first_name,last_name')->findOrFail($id);

        $before = $entry->only(['work_date','check_in','check_out','source','notes','hours_worked']);

        // Validaciones flexibles (acepta distintos formatos)
        $data = $request->validate([
            'work_date' => ['sometimes','date'],
            'check_in'  => ['sometimes','nullable','date'],
            'check_out' => ['sometimes','nullable','date'],
            'source'    => ['sometimes','in:portal,forms_csv'],
            'notes'     => ['sometimes','nullable','string'],
        ]);

        foreach (['check_in','check_out','notes'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] === '') {
                $data[$k] = null;
            }
        }

        if (
            array_key_exists('check_in', $data) && array_key_exists('check_out', $data) &&
            !is_null($data['check_in']) && !is_null($data['check_out'])
        ) {
            if (Carbon::parse($data['check_out'])->lte(Carbon::parse($data['check_in']))) {
                return response()->json(['message' => 'check_out debe ser mayor que check_in'], 422);
            }
        }

        // Normaliza
        if (array_key_exists('work_date', $data)) {
            $data['work_date'] = Carbon::parse($data['work_date'])->toDateString();
        }
        if (array_key_exists('check_in', $data)) {
            $data['check_in'] = is_null($data['check_in']) ? null : Carbon::parse($data['check_in'])->format('Y-m-d H:i:s');
        }
        if (array_key_exists('check_out', $data)) {
            $data['check_out'] = is_null($data['check_out']) ? null : Carbon::parse($data['check_out'])->format('Y-m-d H:i:s');
        }

        $entry->fill($data);

        // Recalcular horas si se tocó check_in/out
        if (array_key_exists('check_in', $data) || array_key_exists('check_out', $data)) {
            if (!empty($entry->check_in) && !empty($entry->check_out) &&
                Carbon::parse($entry->check_out)->gt(Carbon::parse($entry->check_in))) {
                $entry->hours_worked = Carbon::parse($entry->check_out)->floatDiffInHours(Carbon::parse($entry->check_in));
            } else {
                $entry->hours_worked = null;
            }
        }

        $entry->save();

        // Auditoría a prueba de fallos
        try {
            $after = $entry->fresh()->only(['work_date','check_in','check_out','source','notes','hours_worked']);
            if (method_exists($this, 'audit')) {
                $this->audit('time_entries.update', [
                    'entry_id' => $entry->id,
                    'employee' => $entry->employee?->code,
                    'before'   => $before,
                    'after'    => $after,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('audit_log_failed', [
                'action' => 'time_entries.update',
                'entry_id' => $entry->id,
                'err' => $e->getMessage(),
            ]);
        }

        $entry->status = $this->computeStatus($entry->check_in, $entry->check_out);

        return response()->json($entry->load('employee'));
    }

    // ===== Helpers =====

    private function calcHours(string $in, string $out): float
    {
        $start = Carbon::parse($in);
        $end   = Carbon::parse($out);
        return max(0, $end->floatDiffInHours($start));
    }

    private function computeStatus($checkIn, $checkOut): string
    {
        if ($checkIn && $checkOut && Carbon::parse($checkOut)->gt(Carbon::parse($checkIn))) {
            return 'completo';
        }
        if ($checkIn && !$checkOut) {
            return 'pendiente_salida';
        }
        return 'anomalia';
    }
}
