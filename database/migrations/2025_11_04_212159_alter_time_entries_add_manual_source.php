<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Si la columna no existe, la creamos según motor.
        if (!Schema::hasColumn('time_entries', 'source')) {
            if ($driver === 'sqlite') {
                Schema::table('time_entries', function (Blueprint $table) {
                    $table->string('source', 20)->nullable()->default(null);
                });

                // Normalizar datos a un valor válido
                DB::table('time_entries')
                    ->whereNull('source')
                    ->update(['source' => 'portal']);
            } else {
                // MySQL/MariaDB: podemos crearla directamente como ENUM NOT NULL DEFAULT
                DB::statement("
                    ALTER TABLE time_entries
                    ADD COLUMN `source` ENUM('portal','forms_csv','manual') NOT NULL DEFAULT 'portal'
                ");
            }

            return;
        }

        // La columna sí existe: normalizar según motor
        if ($driver === 'sqlite') {
            // SQLite NO soporta MODIFY ni ENUM → mantener string y normalizar datos
            DB::table('time_entries')
                ->whereNull('source')
                ->update(['source' => 'portal']);

            DB::table('time_entries')
                ->whereNotIn('source', ['portal','forms_csv','manual'])
                ->update(['source' => 'portal']);

            // (Opcional) Si quisieras forzar NOT NULL en SQLite habría que recrear la tabla; no es necesario para tests.
        } else {
            // MySQL/MariaDB: sí se puede forzar ENUM + NOT NULL + DEFAULT
            DB::statement("
                ALTER TABLE time_entries
                MODIFY `source` ENUM('portal','forms_csv','manual') NOT NULL DEFAULT 'portal'
            ");
        }
    }

    public function down(): void
    {
        // Reversa mínima y segura (no borramos la columna, solo la relajamos en MySQL).
        try {
            if (DB::getDriverName() !== 'sqlite' && Schema::hasColumn('time_entries', 'source')) {
                DB::statement("ALTER TABLE time_entries MODIFY `source` VARCHAR(20) NULL");
            }
        } catch (\Throwable $e) {
            // ignorar en caso de motor/versión que no lo permita
        }
    }
};
