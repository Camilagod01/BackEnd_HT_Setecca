<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Services\AttendanceReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function __construct(private readonly AttendanceReportService $service) {}

    /**
     * GET /api/reports/attendance?from=&to=&search=&employee_id=
     * Retorna JSON con arreglo de filas (por empleado).
     */
    public function index(Request $req)
    {
        $data = $req->validate([
            'from'        => ['required','date'],
            'to'          => ['required','date','after_or_equal:from'],
            'search'      => ['sometimes','string'],
            'employee_id' => ['sometimes','integer','exists:employees,id'],
        ]);

        $rows = $this->service->byEmployee($data['from'], $data['to'], $data);

        return response()->json([
            'from' => $data['from'],
            'to'   => $data['to'],
            'rows' => $rows,
            'count'=> count($rows),
        ]);
    }

    /**
     * GET /api/reports/attendance/export?from=&to=&search=&employee_id=&format=csv
     * Export simple a CSV (sin paquetes).
     */
    public function export(Request $req)
    {
        $data = $req->validate([
            'from'        => ['required','date'],
            'to'          => ['required','date','after_or_equal:from'],
            'search'      => ['sometimes','string'],
            'employee_id' => ['sometimes','integer','exists:employees,id'],
            'format'      => ['sometimes','in:csv'], // si luego agregas xlsx, amplías aquí
        ]);

        $rows = $this->service->byEmployee($data['from'], $data['to'], $data);

        $filename = sprintf('attendance_%s_%s.csv', $data['from'], $data['to']);

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $columns = [
            'employee_id','code','name','position',
            'regular_hours','overtime_15','overtime_20',
            'sick_50pct_days','sick_0pct_days','attendance_days',
            'total','extra_day','extra_week',
        ];

        $callback = function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 (para Excel)
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            foreach ($rows as $r) {
                $line = [];
                foreach ($columns as $c) {
                    $line[] = $r[$c] ?? '';
                }
                fputcsv($out, $line);
            }

            fclose($out);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
