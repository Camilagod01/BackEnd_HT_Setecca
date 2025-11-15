<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
//use App\Services\StatementService;
use App\Services\Statements\StatementService;

use Illuminate\Http\Request;
//use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Writer\XLSX\Writer as XLSXWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use App\Models\Employee;
use App\Models\Garnishment;
use Dompdf\Dompdf;
use Dompdf\Options;



class StatementController extends Controller
{
    public function __construct(private readonly StatementService $svc) {}

    /*public function showByCode($code, Request $req)
    {
        $emp = \App\Models\Employee::where('code', $code)->firstOrFail();
        $data = $this->svc->build($emp->id, $req->query('from'), $req->query('to'));
        return response()->json($data);
    }*/


        public function showByCode(Request $request, string $code)
{
    $employee = Employee::where('code', $code)->firstOrFail();
     return $this->respondByEmployeeId($employee->id, $request);
}


  // ---- helper: arma el estado y responde JSON
    private function respondByEmployeeId(int $employeeId, Request $request)
    {
        $from = $request->query('from');
        $to   = $request->query('to');
        $data = $this->svc->build($employeeId, $from, $to);
        return response()->json($data);
    }




    public function export($id, Request $req)
    {
        $type = strtolower($req->query('type', 'pdf'));
        $from = $req->query('from');
        $to   = $req->query('to');

        $data = $this->svc->build((int)$id, $from, $to);

        $filenameBase = sprintf(
            'estado_%s_%s_a_%s',
            $data['employee']['code'] ?? $id,
            str_replace('-', '', $data['period']['from']),
            str_replace('-', '', $data['period']['to'])
        );

        if (in_array($type, ['excel', 'xlsx'], true)) {
            $filename = sprintf(
                'estado_%s_%s_a_%s.xlsx',
                $data['employee']['code'] ?? $id,
                str_replace('-', '', $data['period']['from']),
                str_replace('-', '', $data['period']['to'])
            );

            $tmpDir = storage_path('app/tmp');
            if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0777, true); }
            $path = $tmpDir . '/' . uniqid('estado_', true) . '.xlsx';

            $writer = new XLSXWriter();
            $writer->openToFile($path);

            // === ESTILOS ===
            $styleBold = (new Style())->setFontBold();

            $styleTitle = (new Style())
                ->setFontBold()
                ->setFontSize(13)
                ->setShouldWrapText(true);

            // Formato moneda; ajusta según la moneda del estado
            $styleMoney = ($data['currency'] === 'USD')
                ? (new Style())->setFormat('$ #,##0.00')
                : (new Style())->setFormat('₡ #,##0.00');

            // === ENCABEZADO PRINCIPAL ===
            $writer->addRow(Row::fromValues(['Estado de cuenta'], $styleTitle));

            // Info general
            $writer->addRows([
                Row::fromValues(['Empleado', $data['employee']['name'], $data['employee']['code']]),
                Row::fromValues(['Período', $data['period']['from'].' a '.$data['period']['to']]),
                Row::fromValues(['Moneda', $data['currency']]),
                Row::fromValues(['Tipo de cambio CRC/USD', $data['exchange_rate']]),
                Row::fromValues([]),
            ]);

            // === HORAS ===
            $writer->addRow(Row::fromValues(['Resumen de horas'], $styleBold));
            $writer->addRows([
                Row::fromValues(['Horas 1x', $data['hours']['regular_1x']]),
                Row::fromValues(['Horas extra 1.5x', $data['hours']['overtime_15']]),
                Row::fromValues(['Horas doble 2x', $data['hours']['double_20']]),
                Row::fromValues([]),
            ]);

            // === INGRESOS ===
            $writer->addRow(Row::fromValues(['Ingresos'], $styleBold));
            foreach ($data['incomes'] as $i) {
                $writer->addRow(Row::fromValues([$i['label'], $i['amount']], $styleMoney));
            }
            $writer->addRow(Row::fromValues(['Total bruto', $data['total_gross']], $styleBold));
            $writer->addRow(Row::fromValues([]));

            // === DEDUCCIONES ===
            $writer->addRow(Row::fromValues(['Deducciones'], $styleBold));
            foreach ($data['deductions'] as $d) {
                $writer->addRow(Row::fromValues([$d['label'], $d['amount']], $styleMoney));
            }
            $writer->addRow(Row::fromValues(['Total deducciones', $data['total_deductions']], $styleBold));
            $writer->addRow(Row::fromValues([]));

            // === NETO FINAL ===
            $writer->addRow(Row::fromValues(['Neto', $data['net']], $styleTitle));

            $writer->close();

            return response()->download(
                $path,
                $filename,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )->deleteFileAfterSend(true);
        }


        /*$pdf = Pdf::loadView('pdf.statement', ['s' => $data]);
        return $pdf->download($filenameBase.'.pdf');*/

        // Render con Dompdf nativo
$html = view('pdf.statement', ['s' => $data])->render();


// Instanciar Dompdf con o sin Options según versión instalada
if (class_exists(\Dompdf\Options::class)) {
    // Dompdf 2.x (o 3.x si mantiene Options)
    $opts = new \Dompdf\Options();
    $opts->set('isRemoteEnabled', true);
    $opts->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($opts);
} else {
    // Dompdf 3.x sin Options
    $dompdf = new Dompdf();
    // set_option existe en 2.x y 3.x
    if (method_exists($dompdf, 'set_option')) {
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->set_option('defaultFont', 'DejaVu Sans');
    }
}



/*$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans'); // evita problemas con tildes/símbolos
$dompdf = new Dompdf($options);*/

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

return response($dompdf->output(), 200, [
    'Content-Type' => 'application/pdf',
    'Content-Disposition' => 'attachment; filename="'.$filenameBase.'.pdf"',
]);

    }


    public function show(Request $request, string $idOrCode)
{
    if (is_numeric($idOrCode)) {
            return $this->respondByEmployeeId((int)$idOrCode, $request);
        }
        return $this->showByCode($request, $idOrCode);
   }
  

protected function showByEmployeeId(int $employeeId, ?string $from = null, ?string $to = null)
{
    try {
            $data = $this->svc->build($employeeId, $from, $to);
            return response()->json($data);
        } catch (\Throwable $e) {
            \Log::error('Statement build failed', [
                'employee_id' => $employeeId,
                'from'        => $from,
                'to'          => $to,
                'err'         => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'   => 'statement_build_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
}

}
