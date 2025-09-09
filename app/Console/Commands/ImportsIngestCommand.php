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
    protected $signature = 'imports:ingest {--fileName=} {--autocreate-employees=false}';
    protected $description = 'Lee marcaciones.csv desde la carpeta sincronizada (Drive) y hace upsert en time_entries';

    // ===== Helpers de diagnóstico =====
    protected array $stats = [
        'ok' => 0,
        'missing_employee' => 0,
        'missing_required' => 0,
        'parse_error' => 0,
        'checkout_before_checkin' => 0,
    ];
    protected array $samples_missing_employee = []; // para mostrar ejemplos

    public function handle(): int
    {
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

        // Reunir archivos a procesar
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
            $base = basename($file);
            $stamp = date('Ymd_His');
            $okPath  = rtrim($okDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $stamp . '_' . $base;
            $errPath = rtrim($errDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $stamp . '_' . $base;

            // reset stats para cada archivo
            $this->stats = ['ok'=>0,'missing_employee'=>0,'missing_required'=>0,'parse_error'=>0,'checkout_before_checkin'=>0];
            $this->samples_missing_employee = [];

            try {
                DB::beginTransaction();

                $csv = new SplFileObject($file, 'r');
                $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
                $csv->setCsvControl(',');

                // Encabezados
                $headers = $csv->fgetcsv();
                if (!$headers || $headers === [null]) throw new \RuntimeException('CSV vacío.');
                $headers = array_map(fn($h) => strtolower(trim((string)$h)), $headers);

                $need = ['employee_code','work_date','check_in','check_out','notes'];
                foreach ($need as $col) {
                    if (!in_array($col, $headers, true)) {
                        throw new \RuntimeException("Falta columna requerida: {$col}");
                    }
                }
                $idx = fn($k) => array_search($k, $headers, true);

                // Procesar filas
                while (!$csv->eof()) {
                    $row = $csv->fgetcsv();
                    if ($row === [null] || $row === false) continue;
                    $row = array_pad($row, count($headers), null);

                    try {
                        $code  = $this->cleanStr((string)($row[$idx('employee_code')] ?? ''));
                        $wd    = $this->cleanStr((string)($row[$idx('work_date')] ?? ''));
                        $cin   = $this->cleanStr((string)($row[$idx('check_in')] ?? ''));
                        $cout  = $this->cleanStr((string)($row[$idx('check_out')] ?? ''));
                        $notes = (string)($row[$idx('notes')] ?? '');

                        if ($code === '' || $wd === '') {
                            $this->stats['missing_required']++;
                            continue;
                        }

                        $emp = $this->findEmployeeByCode($code);
                        if (!$emp && $autoCreate) {
                            // Autocreación básica (opcional). Ajusta campos según tu modelo.
                            $emp = Employee::create([
                                'code'       => $this->normalizeTargetCode($code),
                                'first_name' => 'Desconocido',
                                'last_name'  => $code,
                            ]);
                        }
                        if (!$emp) {
                            $this->stats['missing_employee']++;
                            if (count($this->samples_missing_employee) < 10) {
                                $this->samples_missing_employee[] = $code;
                            }
                            continue;
                        }

                        $tz = 'America/Costa_Rica';

                        // Fecha robusta
                        $workDate = $this->parseWorkDateOrNull($wd, $tz);
                        if (!$workDate) {
                            $this->stats['parse_error']++;
                            if ($this->stats['parse_error'] <= 5) {
                                $this->warn("parse_error(work_date) code={$code} raw='{$wd}'");
                            }
                            continue;
                        }

                        // Horas robustas
                        $checkIn  = $this->parseTimeOrNull($cin, $workDate, $tz);
                        $checkOut = $this->parseTimeOrNull($cout, $workDate, $tz);

                        if ($checkOut && $checkIn && $checkOut->lt($checkIn)) {
                            $this->stats['checkout_before_checkin']++;
                            continue;
                        }

                        // UPSERT (sin 'source' en la clave)
                        TimeEntry::updateOrCreate(
                            ['employee_id' => $emp->id, 'work_date' => $workDate],
                            [
                                'check_in'  => $checkIn,
                                'check_out' => $checkOut,
                                'notes'     => $notes,
                                // 'source'  => 'forms', // agrega solo si existe la columna
                            ]
                        );

                        $this->stats['ok']++;
                    } catch (\Throwable $ex) {
                        $this->stats['parse_error']++;
                        if ($this->stats['parse_error'] <= 5) {
                            $this->warn("parse_error(row) code={$code} wd='{$wd}' in='{$cin}' out='{$cout}'");
                        }
                        continue;
                    }
                }

                DB::commit();

                // Mover archivo si aplica
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
                $this->error("$base → ERROR: ".$e->getMessage());
                Log::error("imports:ingest $base ERROR ".$e->getMessage());
            }
        }

        return 0;
    }

    // ===== Normalizaciones y búsqueda flexible de empleado =====

    protected function findEmployeeByCode(string $raw)
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        if ($e = \App\Models\Employee::where('code', $raw)->first()) return $e;

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits !== '') {
            $pad4 = str_pad($digits, 4, '0', STR_PAD_LEFT);
            if ($e = \App\Models\Employee::where('code', "emp-{$pad4}")->first()) return $e;
        }

        $low = strtolower($raw);
        if ($e = \App\Models\Employee::where('code', $low)->first()) return $e;

        return null;
    }

    protected function normalizeTargetCode(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits !== '') {
            $pad4 = str_pad($digits, 4, '0', STR_PAD_LEFT);
            return "emp-{$pad4}";
        }
        return strtolower(trim($raw));
    }

    /** Limpia BOM, guiones unicode, espacios invisibles, múltiple espacio */
    protected function cleanStr(?string $s): string
    {
        $s = $s ?? '';
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);                 // BOM UTF-8
        $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s);  // zero-width
        $s = preg_replace('/[\x{2010}-\x{2015}]/u', '-', $s);         // guiones raros → '-'
        $s = preg_replace('/\s+/u', ' ', $s);                         // espacios múltiples
        return trim($s);
    }

    /** Fecha robusta → 'Y-m-d' o null */
    protected function parseWorkDateOrNull(string $val, string $tz): ?string
    {
        $v = $this->cleanStr($val);
        if ($v === '') return null;

        $try = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y'];
        foreach ($try as $fmt) {
            try {
                return \Carbon\Carbon::createFromFormat($fmt, $v, $tz)->toDateString();
            } catch (\Throwable $e) {}
        }

        try { return \Carbon\Carbon::parse($v, $tz)->toDateString(); } catch (\Throwable $e) { return null; }
    }

    /** Hora robusta → Carbon o null (HH:mm, HH:mm:ss, fracción día, AM/PM es/en) */
    protected function parseTimeOrNull(string $val, string $workDate, string $tz): ?\Carbon\Carbon
    {
        $v = $this->cleanStr($val);
        if ($v === '' || $workDate === '') return null;

        // 1) HH:mm (24h)
        try { return \Carbon\Carbon::createFromFormat('Y-m-d H:i', "$workDate $v", $tz); } catch (\Throwable $e) {}

        // 2) HH:mm:ss (24h)
        try { return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', "$workDate $v", $tz); } catch (\Throwable $e) {}

        // 3) Fracción de día (ej. 0.5 = 12:00)
        if (is_numeric($v)) {
            $mins = (int) round(((float)$v) * 24 * 60);
            $h = (int) floor($mins / 60);
            $m = (int) ($mins % 60);
            $hm = sprintf('%02d:%02d', max(0, min(23, $h)), max(0, min(59, $m)));
            try { return \Carbon\Carbon::createFromFormat('Y-m-d H:i', "$workDate $hm", $tz); } catch (\Throwable $e) {}
        }

        // 4) AM/PM español/inglés
        $v2 = preg_replace('/a\.?\s*m\.?/i', 'AM', $v);
        $v2 = preg_replace('/p\.?\s*m\.?/i', 'PM', $v2);
        $v2 = str_ireplace([' a. m.',' p. m.'], [' AM',' PM'], $v2);
        try { return \Carbon\Carbon::parse("$workDate $v2", $tz); } catch (\Throwable $e) {}

        return null;
    }

    protected function buildSummary(string $base): string
    {
        $msg = "{$base} → OK={$this->stats['ok']} ERR=" .
               ($this->stats['missing_employee'] + $this->stats['missing_required'] + $this->stats['parse_error'] + $this->stats['checkout_before_checkin']) .
               "  [faltan_emp={$this->stats['missing_employee']}, req={$this->stats['missing_required']}, parse={$this->stats['parse_error']}, out<in={$this->stats['checkout_before_checkin']}]";

        if ($this->stats['missing_employee'] > 0 && count($this->samples_missing_employee) > 0) {
            $msg .= '  Ejemplos códigos no encontrados: ' . implode(', ', $this->samples_missing_employee);
        }
        return $msg;
    }
}
