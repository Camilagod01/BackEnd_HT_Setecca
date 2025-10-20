<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Agregar columnas nuevas si faltan
        Schema::table('advances', function (Blueprint $table) {
            // currency
            if (!Schema::hasColumn('advances', 'currency')) {
                // ENUM CRC/USD con default CRC
                DB::statement("ALTER TABLE advances ADD COLUMN `currency` ENUM('CRC','USD') NOT NULL DEFAULT 'CRC' AFTER amount");
            }

            // granted_at (primero lo agregamos como NULL para poder poblarlo)
            if (!Schema::hasColumn('advances', 'granted_at')) {
                $table->date('granted_at')->nullable()->after('currency');
            }

            // scheduling_json
            if (!Schema::hasColumn('advances', 'scheduling_json')) {
                $table->json('scheduling_json')->nullable()->after('status');
            }

            // created_by / updated_by
            if (!Schema::hasColumn('advances', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('scheduling_json');
            }
            if (!Schema::hasColumn('advances', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
        });

        // 2) Poblar granted_at con request_date si existe
        if (Schema::hasColumn('advances', 'granted_at') && Schema::hasColumn('advances', 'request_date')) {
            DB::statement("UPDATE advances SET granted_at = request_date WHERE granted_at IS NULL AND request_date IS NOT NULL");
        }

        // 3) Asegurar que granted_at sea NOT NULL (si ya tiene datos)
        //    Si tu tabla está vacía, este ALTER pasará igual.
        if (Schema::hasColumn('advances', 'granted_at')) {
            DB::statement("ALTER TABLE advances MODIFY granted_at DATE NOT NULL");
        }

        // 4) Normalizar status a ENUM('pending','applied','cancelled') DEFAULT 'pending'
        //    - Mapeo sugerido: 'approved' -> 'applied'
        if (Schema::hasColumn('advances', 'status')) {
            DB::statement("UPDATE advances SET status = 'applied' WHERE status = 'approved'");
            // Cambiar tipo a ENUM con default 'pending'
            DB::statement("ALTER TABLE advances MODIFY status ENUM('pending','applied','cancelled') NOT NULL DEFAULT 'pending'");
        }

        // 5) Establecer currency por defecto 'CRC' a filas existentes si quedó null (por seguridad)
        if (Schema::hasColumn('advances', 'currency')) {
            DB::statement("UPDATE advances SET currency = 'CRC' WHERE currency IS NULL OR currency = ''");
        }

        // 6) Crear índice (employee_id, granted_at) si no existe
        //    No hay una API de 'si no existe' en Schema para índices compuestos; usamos try/catch.
        try {
            DB::statement("CREATE INDEX advances_emp_date_idx ON advances (employee_id, granted_at)");
        } catch (\Throwable $e) {
            // índice ya existe o motor no lo permite; ignoramos
        }

        // 7) (Opcional) Remover request_date si ya migramos a granted_at
        if (Schema::hasColumn('advances', 'request_date')) {
            try {
                DB::statement("ALTER TABLE advances DROP COLUMN request_date");
            } catch (\Throwable $e) {
                // Si tu MySQL no permite DROP COLUMN por permisos/versiones, puedes dejarla convivir sin problema.
            }
        }
    }

    public function down(): void
    {
        // Revertir con cuidado: no eliminaremos datos.
        // 1) Re-crear request_date opcionalmente
        if (!Schema::hasColumn('advances', 'request_date')) {
            try {
                DB::statement("ALTER TABLE advances ADD COLUMN request_date DATE NULL");
                DB::statement("UPDATE advances SET request_date = granted_at WHERE request_date IS NULL");
            } catch (\Throwable $e) {}
        }

        // 2) Devolver status a VARCHAR (no estrictamente necesario)
        try {
            DB::statement("ALTER TABLE advances MODIFY status VARCHAR(255) NOT NULL DEFAULT 'approved'");
        } catch (\Throwable $e) {}

        // 3) Quitar índice compuesto
        try {
            DB::statement("DROP INDEX advances_emp_date_idx ON advances");
        } catch (\Throwable $e) {}

        // 4) Quitar columnas nuevas (opcional, para no arriesgar datos normalmente lo dejamos)
        // Schema::table('advances', function (Blueprint $table) {
        //     if (Schema::hasColumn('advances', 'currency')) $table->dropColumn('currency');
        //     if (Schema::hasColumn('advances', 'granted_at')) $table->dropColumn('granted_at');
        //     if (Schema::hasColumn('advances', 'scheduling_json')) $table->dropColumn('scheduling_json');
        //     if (Schema::hasColumn('advances', 'created_by')) $table->dropColumn('created_by');
        //     if (Schema::hasColumn('advances', 'updated_by')) $table->dropColumn('updated_by');
        // });
    }
};
