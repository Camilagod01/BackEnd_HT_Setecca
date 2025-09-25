<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class TimeEntryExportController extends Controller
{
    // Export global (con filtros opcionales)
    public function global(Request $req)
    {
        return $this->export($req);
    }

    // Export por empleado (id en la URL)
    public function byEmployee(Employee $employee, Request $req)
    {
        $req->merge(['employee_id' => $employee->id]);
        return $this->export($req);
    }

    protected function export(Request $req)
    {
        $validated = $req->validate([
            'employee'    => 'nullable|string',    // búsqueda por código/nombre (solo global)
            'employee_id' => 'nullable|integer',   // seteado en byEmployee
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
            'status'      => 'nullable|in:completo,pendiente_salida,anómala',
            'format'      => 'nullable|in:csv',    // Solo CSV permitido
        ]);

        // Query base
        $q = TimeEntry::query()
            ->join('employees', 'employees.id', '=', 'time_entries.employee_id')
            ->select([
                'time_entries.work_date as work_date',
                'employees.code as employee_code',
                DB::raw("TRIM(CONCAT_WS(' ', COALESCE(employees.first_name,''), COALESCE(employees.last_name,''))) as employee_name"),
                'time_entries.check_in',
                'time_entries.check_out',
                'time_entries.source',
                'time_entries.notes',
            ]);

        // Filtros
        if (!empty($validated['employee_id'])) {
            $q->where('employees.id', $validated['employee_id']);
        } elseif (!empty($validated['employee'])) {
            $term = $validated['employee'];
            $q->where(function ($w) use ($term) {
                $w->where('employees.code', 'like', "%{$term}%")
                  ->orWhereRaw(
                      "TRIM(CONCAT_WS(' ', COALESCE(employees.first_name,''), COALESCE(employees.last_name,''))) LIKE ?",
                      ["%{$term}%"]
                  );
            });
        }

        if (!empty($validated['from'])) {
            $q->whereDate('time_entries.work_date', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $q->whereDate('time_entries.work_date', '<=', $validated['to']);
        }

        // Orden (evitar alias por compatibilidad)
        $q->orderBy('time_entries.work_date', 'asc')
          ->orderBy('employees.code', 'asc');

        // Generar CSV por streaming
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="marcaciones_'.now()->format('Ymd_His').'.csv"',
            'Cache-Control'       => 'no-store, no-cache',
        ];

        return new StreamedResponse(function () use ($q, $validated) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));
            // Encabezados
            fputcsv($out, ['fecha','empleado_code','empleado_nombre','entrada','salida','horas','estado','observaciones','origen']);

            $q->chunk(500, function ($rows) use ($out, $validated) {
                foreach ($rows as $r) {
                    [$estado, $horas] = $this->computeStatusAndHours($r->check_in, $r->check_out);

                    // Aplicar filtro de estado (si se pidió)
                    if (!empty($validated['status']) && $estado !== $validated['status']) {
                        continue;
                    }

                    fputcsv($out, [
                        $this->fmtDate($r->work_date),
                        $r->employee_code,
                        $r->employee_name,
                        $this->fmtTime($r->check_in),
                        $this->fmtTime($r->check_out),
                        $horas,
                        $estado,
                        $r->notes,
                        $r->source,
                    ]);
                }
            });
            fclose($out);
        }, 200, $headers);
    }

    /** ---- Helpers ---- */
    private function fmtDate($date): ?string
    {
        if (!$date) return null;
        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    private function fmtTime($dt): ?string
    {
        if (!$dt) return null;
        try {
            return Carbon::parse($dt)->format('H:i');
        } catch (\Throwable $e) {
            return (string) $dt;
        }
    }

    private function computeStatusAndHours($checkIn, $checkOut): array
    {
        if (!$checkIn && !$checkOut) {
            return ['anómala', null];
        }
        if ($checkIn && !$checkOut) {
            return ['pendiente_salida', null];
        }

        try {
            $in  = Carbon::parse($checkIn);
            $out = Carbon::parse($checkOut);
        } catch (\Throwable $e) {
            return ['anómala', null];
        }

        if ($out->lt($in)) {
            return ['anómala', null];
        }

        $minutes = $in->diffInMinutes($out);
        $horas = round($minutes / 60, 2);

        return ['completo', $horas];
    }

    public function byEmployeeId($id, Request $req)
    {
        $employee = Employee::findOrFail($id);
        $req->merge(['employee_id' => $employee->id]);
        return $this->export($req);
    }
}
