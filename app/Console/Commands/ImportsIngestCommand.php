<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\TimeEntry;
use SplFileObject;

class ImportsIngestCommand extends Command
{
    protected $signature = 'imports:ingest {--fileName=} {--autocreate-employees=false} {--debug}';
    protected $description = 'Lee CSV de marcaciones y hace upsert en time_entries. Soporta encabezados en español/inglés.';

    /** Métricas */
    protected array $stats = [
        'ok' => 0,
        'missing_employee' => 0,
        'missing_required' => 0,
        'parse_error' => 0,
        'checkout_before_checkin' => 0,
    ];
    protected array $samples_missing_employee = [];

    /** Zona horaria por defecto */
    protected string $tz = 'America/Costa_Rica';

    public function handle(): int
    {
        $debug      = (bool) $this->option('debug');
        $watchDir   = env('IMPORT_WATCH_DIR');
        $okDir      = env('IMPORT_PROCESSED_DIR');
        $errDir     = env('IMPORT_ERRORS_DIR');
        $moveFiles  = filter_var(env('IMPORT_MOVE_PROCESSED', true), FILTER_VALIDATE_BOOLEAN);
        $baseName   = $this->option('fileName') ?: (env('IMPORT_FILE_NAME', 'marcaciones.csv'));
        $autoCreate = filter_var($this->option('autocreate-employees'), FILTER_VALIDATE_BOOLEAN);

        if (!$watchDir || !$okDir || !$errDir) {
            $this->error('Revisa .env: IMPORT_WATCH_DIR, IMPORT_PROCESSED_DIR, IMPORT_ERRORS_DIR');
            return 1;
        }

        foreach ([$watchDir, $okDir, $errDir] as $dir) {
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            if (!is_dir($dir)) {
                $this->error("No se pudo acceder/crear: $dir");
                return 1;
            }
        }

        // Archivos candidatos
        $candidates = [];
        $main = rtrim($watchDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseName;
        if (is_file($main)) $candidates[] = $main;
        if (empty($candidates)) {
            $others = glob(rtrim($watchDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.csv') ?: [];
            $candidates = array_values($others);
        }
        if (empty($candidates)) {
            $this->info("No hay CSV por procesar en: $watchDir");
            return 0;
        }

        foreach ($candidates as $file) {
            $base  = basename($file);
            $stamp = date('Ymd_His');
            $okPath  = rtrim($okDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$stamp.'_'.$base;
            $errPath = rtrim($errDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$stamp.'_'.$base;

            // reset stats por archivo
            $this->stats = [
                'ok'=>0,
                'missing_employee'=>0,
                'missing_required'=>0,
                'parse_error'=>0,
                'checkout_before_checkin'=>0
            ];
            $this->samples_missing_employee = [];

            try {
                DB::beginTransaction();

                $csv = new SplFileObject($file, 'r');
                $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

                // Detecta delimitador (coma o punto y coma) con limpieza de BOM
                $firstLineRaw = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)[0] ?? '';
                $firstLine = ltrim($firstLineRaw, "\xEF\xBB\xBF");
                $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
                $csv->setCsvControl($delimiter);

                // Encabezados
                $headers = $csv->fgetcsv();
                if (!$headers || $headers === [null]) {
                    throw new \RuntimeException('CSV vacío.');
                }
                $headers = array_map(fn($h) => $this->normHeader((string)$h), $headers);

                // DEBUG: dentro del loop (ya existen $file/$delimiter/$headers)
                if ($debug) {
                    $this->info('DEBUG: archivo ' . $file . ' con delimitador "' . $delimiter . '"');
                    $this->info('DEBUG: headers normalizados => ' . implode(' | ', $headers));
                }

                // Mapeo de headers a canónicos
                $map = $this->mapHeaders($headers);
                $required = ['employee_code','work_date','check_in','check_out','notes']; // source opcional
                foreach ($required as $rk) {
                    if (!isset($map[$rk])) {
                        throw new \RuntimeException("Falta columna requerida (o alias): {$rk}");
                    }
                }

                // Procesar filas
                while (!$csv->eof()) {
                    $row = $csv->fgetcsv();
                    if ($row === [null] || $row === false) continue;
                    $row = array_pad($row, count($headers), null);

                    try {
                        $raw = fn(string $canonical) => (string)($row[$map[$canonical]] ?? '');

                        $code  = trim($raw('employee_code'));
                        $wdRaw = $raw('work_date');
                        $cin   = $raw('check_in');
                        $cout  = $raw('check_out');
                        $notes = $raw('notes');
                        $src = isset($map['source']) ? trim((string) $raw('source')) : '';
                        if ($src === '' || strtolower($src) === 'null') {
                            $src = 'forms_csv';
                        }

                        if ($code === '' || trim($wdRaw) === '') {
                            $this->stats['missing_required']++;
                            if ($debug) $this->warn('DEBUG missing_required → code="'.$code.'" wdRaw="'.$wdRaw.'"');
                            continue;
                        }

                        // Empleado
                        $emp = $this->findEmployeeByCode($code);
                        if (!$emp && $autoCreate) {
                            $emp = Employee::create([
                                'code'       => $this->normalizeTargetCode($code),
                                'first_name' => 'Desconocido',
                                'last_name'  => $code,
                                'status'     => 'active',
                            ]);
                        }
                        if (!$emp) {
                            $this->stats['missing_employee']++;
                            if (count($this->samples_missing_employee) < 10) $this->samples_missing_employee[] = $code;
                            if ($debug) $this->warn('DEBUG missing_employee → code="'.$code.'"');
                            continue;
                        }

                        // Fecha trabajo
                        $workDate = $this->parseWorkDateOrNull($wdRaw, $this->tz);
                        if (!$workDate) {
                            $this->stats['parse_error']++;
                            if ($debug) $this->warn('DEBUG parse_work_date fail → work_date_raw="'.$wdRaw.'" (code="'.$code.'")');
                            continue;
                        }

                        // Horas (acepta 24h y AM/PM español/inglés)
                        $checkIn  = $this->parseTimeOrNull($cin,  $workDate, $this->tz);
                        $checkOut = $this->parseTimeOrNull($cout, $workDate, $this->tz);

                        if ($checkOut && $checkIn && $checkOut->lt($checkIn)) {
                            $this->stats['checkout_before_checkin']++;
                            if ($debug) $this->warn('DEBUG out<in → in="'.$cin.'" out="'.$cout.'" work_date="'.$workDate.'" code="'.$code.'"');
                            continue;
                        }

                        // UPSERT
                        TimeEntry::updateOrCreate(
                            ['employee_id' => $emp->id, 'work_date' => $workDate, 'source' => $src],
                            ['check_in' => $checkIn, 'check_out' => $checkOut, 'notes' => $notes]
                        );

                        $this->stats['ok']++;
                        if ($debug) $this->info('DEBUG OK → code="'.$code.'" wd="'.$workDate.'" in="'.$cin.'" out="'.$cout.'"');
                    } catch (\Throwable $ex) {
                        $this->stats['parse_error']++;
                        if ($debug) {
                            $this->warn('DEBUG parse_error row → '.json_encode([
                                'employee_code' => $code ?? null,
                                'work_date_raw' => $wdRaw ?? null,
                                'check_in_raw'  => $cin ?? null,
                                'check_out_raw' => $cout ?? null,
                                'error'         => $ex->getMessage(),
                            ]));
                        }
                        Log::warning('imports:ingest parse_error', ['row' => $row, 'err' => $ex->getMessage()]);
                        continue;
                    }
                }

                DB::commit();

                if ($moveFiles) {
                    @rename($file, $okPath);
                }

                $summary = $this->buildSummary($base);
                $this->info($summary);
                Log::info("imports:ingest " . $summary);
            } catch (\Throwable $e) {
                DB::rollBack();
                if ($moveFiles) {
                    @rename($file, $errPath);
                }
                $this->error("$base → ERROR: " . $e->getMessage());
                Log::error("imports:ingest $base ERROR " . $e->getMessage());
            }
        }

        return 0;
    }

    /* ===================== Helpers ===================== */

    /** Normaliza encabezados: minúsculas, sin tildes, espacios a una sola, quita BOM */
    protected function normHeader(string $h): string
    {
        $h = ltrim($h, "\xEF\xBB\xBF"); // BOM
        $h = trim(mb_strtolower($h));
        $h = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $h);
        $h = preg_replace('/\s+/', ' ', $h);
        return $h;
    }

    /** Mapea encabezados reales a canónicos esperados */
    protected function mapHeaders(array $headers): array
    {
        // aliases por cada columna canónica
        $aliases = [
            'employee_code' => [
                'employee_code','code','codigo','codigo de empleado','codigo empleado','código de empleado'
            ],
            'work_date' => [
                'work_date','fecha','fecha de trabajo','marca temporal','timestamp','marca temporal (fecha y hora)'
            ],
            'check_in' => [
                'check_in','entrada','hora de entrada','ingreso','check in'
            ],
            'check_out' => [
                'check_out','salida','hora de salida','egreso','check out'
            ],
            'notes' => [
                'notes','nota','notas','comentarios','observaciones'
            ],
            'source' => [
                'source','fuente','origen'
            ],
        ];

        $map = [];
        foreach ($aliases as $canonical => $cands) {
            foreach ($cands as $cand) {
                $i = array_search($this->normHeader($cand), $headers, true);
                if ($i !== false) { $map[$canonical] = $i; break; }
            }
        }
        return $map;
    }

    /** Busca empleado por código en varias variantes */
    protected function findEmployeeByCode(string $raw): ?Employee
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        if ($e = Employee::where('code', $raw)->first()) return $e;

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits !== '') {
            $pad4 = str_pad($digits, 4, '0', STR_PAD_LEFT);
            if ($e = Employee::where('code', "emp-{$pad4}")->first()) return $e;
        }

        $low = strtolower($raw);
        if ($e = Employee::where('code', $low)->first()) return $e;

        return null;
    }

    /** Normaliza destino para autocreación */
    protected function normalizeTargetCode(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits !== '') {
            $pad4 = str_pad($digits, 4, '0', STR_PAD_LEFT);
            return "emp-{$pad4}";
        }
        return strtolower(trim($raw));
    }

    /** Fecha de trabajo robusta: ISO, dd/mm/yyyy [hh:mm[:ss]] */
    protected function parseWorkDateOrNull(string $val, string $tz): ?string
    {
        $v = trim($val);
        if ($v === '') return null;

        // Limpieza de comillas/espacios raros (incluye NBSP)
        $v = trim($v, "\"' \u{00A0}");

        // 1) ISO puro yyyy-mm-dd
        try { return Carbon::createFromFormat('Y-m-d', $v, $tz)->toDateString(); } catch (\Throwable $e) {}

        // 2) ISO con hora
        foreach (['Y-m-d H:i:s','Y-m-d H:i'] as $fmt) {
            try { return Carbon::createFromFormat($fmt, $v, $tz)->toDateString(); } catch (\Throwable $e) {}
        }

        // 3) dd/mm/yyyy con/sin hora
        foreach (['d/m/Y H:i:s','d/m/Y H:i','d/m/Y'] as $fmt) {
            try { return Carbon::createFromFormat($fmt, $v, $tz)->toDateString(); } catch (\Throwable $e) {}
        }

        // 4) dd/mm/yy con/sin hora
        foreach (['d/m/y H:i:s','d/m/y H:i','d/m/y'] as $fmt) {
            try { return Carbon::createFromFormat($fmt, $v, $tz)->toDateString(); } catch (\Throwable $e) {}
        }

        // 5) dd-mm-yyyy con/sin hora
        foreach (['d-m-Y H:i:s','d-m-Y H:i','d-m-Y'] as $fmt) {
            try { return Carbon::createFromFormat($fmt, $v, $tz)->toDateString(); } catch (\Throwable $e) {}
        }

        // 6) Último intento libre
        try { return Carbon::parse($v, $tz)->toDateString(); } catch (\Throwable $e) { return null; }
    }

    /** Hora robusta: 24h y AM/PM con variantes español/inglés */
    protected function parseTimeOrNull(string $val, string $workDate, string $tz): ?Carbon
    {
        $v = trim($val);
        if ($v === '') return null;

        // 24h directas
        foreach (['Y-m-d H:i:s','Y-m-d H:i'] as $fmt) {
            try { return Carbon::createFromFormat($fmt, "$workDate $v", $tz); } catch (\Throwable $e) {}
        }

        // Normaliza "a. m." / "p. m." / "am" / "pm" → " AM"/" PM"
        $vNorm = preg_replace_callback('/\b([ap])\s*\.?\s*m\.?\b/iu', function ($m) {
            return ' ' . strtoupper($m[1]) . 'M';
        }, $v);
        $vNorm = preg_replace('/\b(am|pm)\b/i', fn($m) => ' '.strtoupper($m[0]), $vNorm);

        try { return Carbon::parse("$workDate $vNorm", $tz); } catch (\Throwable $e) {}

        return null;
    }

    /** Resumen tipo: file → OK=n ERR=m [faltan_emp=x, req=y, parse=z, out<in=w] */
    protected function buildSummary(string $base): string
    {
        $errors = $this->stats['missing_employee']
                + $this->stats['missing_required']
                + $this->stats['parse_error']
                + $this->stats['checkout_before_checkin'];

        $msg = "{$base} → OK={$this->stats['ok']} ERR={$errors}  ".
               "[faltan_emp={$this->stats['missing_employee']}, req={$this->stats['missing_required']}, parse={$this->stats['parse_error']}, out<in={$this->stats['checkout_before_checkin']}]";

        if ($this->stats['missing_employee'] > 0 && count($this->samples_missing_employee) > 0) {
            $msg .= '  Ejemplos códigos no encontrados: ' . implode(', ', $this->samples_missing_employee);
        }
        return $msg;
    }
}