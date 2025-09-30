<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use App\Traits\AuditsChanges;
use Illuminate\Support\Carbon;

class TimeEntryController extends Controller
{
    use AuditsChanges;

    /**
     * GET /api/time-entries
     * Filtros:
     * - q: busca por code o "nombre apellido"
     * - employee_id: exacto
     * - date: YYYY-MM-DD (compat anterior)
     * - from, to: rango YYYY-MM-DD
     * - status: completo | pendiente_salida | anomalia
     * - page: paginación
     */
    public function index(Request $request)
    {
        $q = TimeEntry::with(['employee:id,code,first_name,last_name'])
            ->orderByDesc('work_date')
            ->orderByDesc('id');

        // Compat anterior: date exacta (sigue funcionando)
        if ($date = $request->get('date')) {
            $q->whereDate('work_date', $date);
        }

        // Compat anterior: employee_id exacto
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

        // Rango de fechas: from / to
        $from = $request->get('from');
        $to   = $request->get('to');
        if ($from && $to) {
            $q->whereBetween('work_date', [$from, $to]);
        } elseif ($from) {
            $q->whereDate('work_date', '>=', $from);
        } elseif ($to) {
            $q->whereDate('work_date', '<=', $to);
        }

        // Estado: completo | pendiente_salida | anomalia
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
                    // Anomalía: salida < entrada o datos faltantes (excepto “pendiente_salida”)
                    $w->whereNull('check_in')
                      ->orWhere(function ($x) {
                          $x->whereNotNull('check_in')
                            ->whereNotNull('check_out')
                            ->whereColumn('check_out', '<', 'check_in');
                      });
                }
            });
        }

        $paginator = $q->paginate(20);

        // Agregar status calculado a cada item (no se persiste, solo salida)
        $paginator->setCollection(
            $paginator->getCollection()->map(function ($row) {
                $row->status = $this->computeStatus($row->check_in, $row->check_out);
                return $row;
            })
        );

        return response()->json($paginator);
    }

    /**
     * POST /api/time-entries
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required','exists:employees,id'],
            'work_date'   => ['required','date'],
            'check_in'    => ['required','date_format:Y-m-d H:i:s'],
            'check_out'   => ['nullable','date_format:Y-m-d H:i:s','after:check_in'],
            'source'      => ['required','in:portal,forms_csv'],
            'notes'       => ['nullable','string'],
        ]);

        $entry = TimeEntry::create($data);

        if (!empty($entry->check_out)) {
            $entry->hours_worked = $this->calcHours($entry->check_in, $entry->check_out);
            $entry->save();
        }

        // Status calculado en respuesta
        $entry->status = $this->computeStatus($entry->check_in, $entry->check_out);

        return response()->json($entry->load('employee'), 201);
    }

    /**
     * PATCH /api/time-entries/{id}
     * Edición con validaciones y auditoría
     */
    public function update($id, Request $request)
    {
        $entry = TimeEntry::with('employee:id,code,first_name,last_name')->findOrFail($id);

        // Tomar snapshot ANTES de modificar
        $before = $entry->only(['work_date','check_in','check_out','source','notes','hours_worked']);

        // Validaciones FLEXIBLES (acepta Y-m-d H:i o H:i:s y también sólo notas)
        $data = $request->validate([
            'work_date' => ['sometimes','date'],
            'check_in'  => ['sometimes','nullable','date'],
            'check_out' => ['sometimes','nullable','date'],
            'source'    => ['sometimes','in:portal,forms_csv'],
            'notes'     => ['sometimes','nullable','string'],
        ]);

        // Si el frontend envía string vacío, conviértelo a null
        foreach (['check_in','check_out','notes'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] === '') {
                $data[$k] = null;
            }
        }

        // Validación lógica SOLO si ambos vienen presentes y no son null
        if (
            array_key_exists('check_in', $data) && array_key_exists('check_out', $data) &&
            !is_null($data['check_in']) && !is_null($data['check_out'])
        ) {
            if (\Carbon\Carbon::parse($data['check_out'])->lte(\Carbon\Carbon::parse($data['check_in']))) {
                return response()->json(['message' => 'check_out debe ser mayor que check_in'], 422);
            }
        }

        // Normalizar formatos a lo que espera la BD
        $fmtDateTime = function ($v) {
            if (is_null($v) || $v === '') return null;
            return \Carbon\Carbon::parse($v)->format('Y-m-d H:i:s'); // fuerza segundos
        };
        $fmtDate = function ($v) {
            if (is_null($v) || $v === '') return null;
            return \Carbon\Carbon::parse($v)->toDateString(); // YYYY-MM-DD
        };

        if (array_key_exists('work_date', $data)) {
            $data['work_date'] = $fmtDate($data['work_date']);
        }
        if (array_key_exists('check_in', $data)) {
            $data['check_in'] = $fmtDateTime($data['check_in']);
        }
        if (array_key_exists('check_out', $data)) {
            $data['check_out'] = $fmtDateTime($data['check_out']);
        }


        // Persistir cambios
        $entry->fill($data);

        // Recalcular horas SOLO si el payload tocó alguno de los dos campos
        if (array_key_exists('check_in', $data) || array_key_exists('check_out', $data)) {
            if (!empty($entry->check_in) && !empty($entry->check_out) &&
                \Carbon\Carbon::parse($entry->check_out)->gt(\Carbon\Carbon::parse($entry->check_in))) {
                $entry->hours_worked = \Carbon\Carbon::parse($entry->check_out)->floatDiffInHours(\Carbon\Carbon::parse($entry->check_in));
            } else {
                $entry->hours_worked = null;
            }
        }

        $entry->save();

        // AUDITORÍA a prueba de fallos: no dejes que rompa la respuesta
        try {
            $after = $entry->fresh()->only(['work_date','check_in','check_out','source','notes','hours_worked']);

            // Usa la variante por "evento" (más segura que pasar el modelo al trait si este asume cosas)
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
            // no interrumpir
        }

        // Status calculado al vuelo para la respuesta
        $entry->status = (function ($in, $out) {
            if ($in && $out && \Carbon\Carbon::parse($out)->gt(\Carbon\Carbon::parse($in))) return 'completo';
            if ($in && !$out) return 'pendiente_salida';
            return 'anomalia';
        })($entry->check_in, $entry->check_out);

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
        return 'anomalia'; // faltantes o salida < entrada
    }
}
