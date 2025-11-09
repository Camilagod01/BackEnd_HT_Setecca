<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Si lo necesita la tabla
        if (Schema::hasColumn('loans', 'start_date')) {
            // Usamos SQL directo para evitar requerir doctrine/dbal
            //DB::statement("ALTER TABLE loans MODIFY start_date DATE NULL");
            // MySQL/MariaDB permiten MODIFY; SQLite no.
if (\DB::getDriverName() !== 'sqlite') {
    try {
        \DB::statement("ALTER TABLE loans MODIFY start_date DATE NULL");
    } catch (\Throwable $e) {
        // si ya está aplicado o el motor no lo permite, lo ignoramos
    }
} else {
    // En SQLite no hacemos ALTER/MODIFY; se deja como esté para tests.
}

        } else {
            Schema::table('loans', function (Blueprint $table) {
                $table->date('start_date')->nullable()->after('granted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('loans', 'start_date')) {
            DB::statement("ALTER TABLE loans MODIFY start_date DATE NOT NULL");
        }
    }
};
