<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportEmployeesRequest;
use App\Models\Employee;
use Illuminate\Support\Facades\Storage;

class EmployeeImportController extends Controller
{
    public function import(ImportEmployeesRequest $request)
    {
        $file = $request->file('file');
        $dryRun = (bool) $request->boolean('dry_run');
        $delimiter = $request->input('delimiter', ',');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if (!$handle) {
            return response()->json(['message' => 'No se pudo leer el archivo.'], 422);
        }

        // Lee encabezados
        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            return response()->json(['message' => 'CSV sin encabezados.'], 422);
        }
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        // Campos esperados (mínimos)
        $required = ['employee_code','first_name','last_name'];
        foreach ($required as $r) {
            if (!in_array($r, $headers, true)) {
                return response()->json([
                    'message' => "Falta la columna requerida: {$r}"
                ], 422);
            }
        }

        $idx = array_flip($headers);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $invalid = 0;

        $errorLines = [];
        $lineNum = 1; // encabezado es 1

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNum++;

            // Salta líneas vacías
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                $skipped++;
                continue;
            }

            $data = [
                'employee_code' => trim((string)($row[$idx['employee_code']] ?? '')),
                'first_name'    => trim((string)($row[$idx['first_name']] ?? '')),
                'last_name'     => trim((string)($row[$idx['last_name']] ?? '')),
                'email'         => isset($idx['email']) ? trim((string)($row[$idx['email']] ?? '')) : null,
                'position_code' => isset($idx['position_code']) ? trim((string)($row[$idx['position_code']] ?? '')) : null,
            ];

            // Validaciones mínimas
            if ($data['employee_code'] === '' || $data['first_name'] === '' || $data['last_name'] === '') {
                $invalid++;
                $errorLines[] = [$lineNum, 'Campos requeridos vacíos'];
                continue;
            }

            // Busca empleado por code
            $emp = Employee::where('code', $data['employee_code'])->first();

            if ($dryRun) {
                // solo contar qué pasaría
                if ($emp) $updated++; else $created++;
                continue;
            }

            // Mapeo a columnas reales del modelo
            $payload = [
                'code'        => $data['employee_code'],
                'first_name'  => $data['first_name'],
                'last_name'   => $data['last_name'],
            ];
            if ($data['email']) $payload['email'] = $data['email'];

            // (Opcional) resolver position_code -> position_id si envían esa columna
            if ($data['position_code']) {
                $positionId = \App\Models\Position::where('code', $data['position_code'])->value('id');
                if ($positionId) $payload['position_id'] = $positionId;
            }

            if ($emp) {
                $emp->fill($payload)->save();
                $updated++;
            } else {
                Employee::create($payload);
                $created++;
            }
        }

        fclose($handle);

        // Genera CSV de errores (opcional)
        $errorsUrl = null;
        if (!empty($errorLines)) {
            $outName = 'import_errors/employees_errors_' . now()->format('Ymd_His') . '.csv';
            $stream = fopen('php://temp', 'w+');
            fputcsv($stream, ['line','error']);
            foreach ($errorLines as $err) fputcsv($stream, $err);
            rewind($stream);
            Storage::disk('public')->put($outName, stream_get_contents($stream));
            fclose($stream);
            $errorsUrl = Storage::url($outName);
        }

        return response()->json([
            'created'        => $created,
            'updated'        => $updated,
            'skipped'        => $skipped,
            'invalid_rows'   => $invalid,
            'errors_csv_url' => $errorsUrl,
        ]);
    }
}
