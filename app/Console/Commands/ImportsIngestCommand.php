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
                        $code  = trim((string)($row[$idx('employee_code')] ?? ''));
                        $wd    = (string)($row[$idx('work_date')] ?? '');
                        $cin   = (string)($row[$idx('check_in')] ?? '');
                        $cout  = (string)($row[$idx('check_out')] ?? '');
                        $notes = (string)($row[$idx('notes')] ?? '');

                        if ($code === '' || $wd === '') {
                            $this->stats['missing_required']++; 
                            continue;
                        }

                        $emp = $this->findEmployeeByCode($code);
                        if (!$emp && $autoCreate) {
                            // Autocreación básica (opcional). Ajusta campos según tu modelo.
                            $emp = Employee::create([
                                'code' => $this->normalizeTargetCode($code),
                                'first_name' => 'Desconocido',
                                'last_name' => $code,
                                // agrega otros campos requeridos por tu modelo...
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
                        try {
                            $workDate = Carbon::parse($wd, $tz)->toDateString();
                        } catch (\Throwable $e) {
                            $this->stats['parse_error']++;
                            continue;
                        }

                        $checkIn  = $this->parseTimeOrNull($cin, $workDate, $tz);
                        $checkOut = $this->parseTimeOrNull($cout, $workDate, $tz);

                        if ($checkOut && $checkIn && $checkOut->lt($checkIn)) {
                            $this->stats['checkout_before_checkin']++;
                            continue;
                        }

                        // UPSERT
                        TimeEntry::updateOrCreate(
                            ['employee_id' => $emp->id, 'work_date' => $workDate, 'source' => 'forms'],
                            ['check_in' => $checkIn, 'check_out' => $checkOut, 'notes' => $notes]
                        );

                        $this->stats['ok']++;
                    } catch (\Throwable $ex) {
                        $this->stats['parse_error']++;
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

    protected function parseTimeOrNull(string $val, string $workDate, string $tz): ?Carbon
    {
        $val = trim($val);
        if ($val === '') return null;

        // Si viene "HH:mm"
        if (preg_match('/^\d{1,2}:\d{2}$/', $val)) {
            return Carbon::parse("$workDate $val", $tz);
        }

        // Intento general
        try {
            return Carbon::parse($val, $tz);
        } catch (\Throwable $e) {
            return null;
        }
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
