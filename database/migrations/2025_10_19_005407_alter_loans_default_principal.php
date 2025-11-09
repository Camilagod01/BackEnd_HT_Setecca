<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Si lo necesita la tabla
            if (Schema::hasColumn('loans', 'principal')) {
                //DB::statement("ALTER TABLE loans MODIFY principal DECIMAL(12,2) NOT NULL DEFAULT 0");
                // Forzar NOT NULL + DEFAULT en MySQL/MariaDB; en SQLite se omite (no soporta MODIFY)
if (\DB::getDriverName() !== 'sqlite') {
    try {
        \DB::statement("ALTER TABLE loans MODIFY principal DECIMAL(12,2) NOT NULL DEFAULT 0");
    } catch (\Throwable $e) {
        // Si el motor no soporta MODIFY o ya está aplicado, lo ignoramos en silencio.
    }
} else {
    // SQLite: no hay MODIFY; en tests lo dejamos como está.
    // (Si algún día se requiere, habría que recrear la tabla temporalmente).
}

            } else {
                $table->decimal('principal', 12, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Si lo necesita la tabla
            //DB::statement("ALTER TABLE loans MODIFY principal DECIMAL(12,2) NOT NULL");
            // Forzar NOT NULL + DEFAULT en MySQL/MariaDB; en SQLite se omite (no soporta MODIFY)
if (\DB::getDriverName() !== 'sqlite') {
    try {
        \DB::statement("ALTER TABLE loans MODIFY principal DECIMAL(12,2) NOT NULL DEFAULT 0");
    } catch (\Throwable $e) {
        // Si ya está aplicado o el motor no lo permite, ignora.
    }
} else {
    // SQLite: no hay MODIFY; en tests lo dejamos tal cual.
    // (Si algún día se requiere, habría que recrear la tabla).
}

        });
    }
};
