<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // ====== RUTA SQLITE (TESTS) ======
            Schema::table('advances', function (Blueprint $table) {
                // currency (como TEXT en sqlite; sin ENUM)
                if (!Schema::hasColumn('advances', 'currency')) {
                    $table->string('currency', 3)->nullable()->after('amount');
                }

                // granted_at (dejamos nullable en sqlite; no se puede "MODIFY" a NOT NULL)
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

            // Poblar granted_at con request_date si existe
            if (Schema::hasColumn('advances', 'granted_at') && Schema::hasColumn('advances', 'request_date')) {
                DB::table('advances')
                    ->whereNull('granted_at')
                    ->whereNotNull('request_date')
                    ->update(['granted_at' => DB::raw('request_date')]);
            }

            // Normalizar status -> 'pending'/'applied'/'cancelled' (sin ENUM en sqlite)
            if (Schema::hasColumn('advances', 'status')) {
                DB::table('advances')
                    ->where('status', 'approved')
                    ->update(['status' => 'applied']);
            }

            // currency por defecto 'CRC'
            if (Schema::hasColumn('advances', 'currency')) {
                DB::table('advances')
                    ->whereNull('currency')
                    ->orWhere('currency', '')
                    ->update(['currency' => 'CRC']);
            }

            // Índice compuesto (sqlite lo permite con CREATE INDEX simple)
            try {
                DB::statement("CREATE INDEX advances_emp_date_idx ON advances (employee_id, granted_at)");
            } catch (\Throwable $e) {
                // ya existe: ignorar
            }

            // NO hacemos DROP COLUMN en sqlite (evitar problemas)
            return;
        }

        // ====== RUTA MYSQL/MARIADB (DEV/PROD) ======

        Schema::table('advances', function (Blueprint $table) {
            // currency como ENUM (vía SQL crudo, igual que tenías)
            if (!Schema::hasColumn('advances', 'currency')) {
                DB::statement("ALTER TABLE advances ADD COLUMN `currency` ENUM('CRC','USD') NOT NULL DEFAULT 'CRC' AFTER amount");
            }

            if (!Schema::hasColumn('advances', 'granted_at')) {
                $table->date('granted_at')->nullable()->after('currency');
            }

            if (!Schema::hasColumn('advances', 'scheduling_json')) {
                $table->json('scheduling_json')->nullable()->after('status');
            }

            if (!Schema::hasColumn('advances', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('scheduling_json');
            }
            if (!Schema::hasColumn('advances', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
        });

        // Poblar granted_at con request_date si existe
        if (Schema::hasColumn('advances', 'granted_at') && Schema::hasColumn('advances', 'request_date')) {
            DB::statement("UPDATE advances SET granted_at = request_date WHERE granted_at IS NULL AND request_date IS NOT NULL");
        }

        // Forzar NOT NULL en MySQL
        if (Schema::hasColumn('advances', 'granted_at')) {
            DB::statement("ALTER TABLE advances MODIFY granted_at DATE NOT NULL");
        }

        // Normalizar status a ENUM y default
        if (Schema::hasColumn('advances', 'status')) {
            DB::statement("UPDATE advances SET status = 'applied' WHERE status = 'approved'");
            DB::statement("ALTER TABLE advances MODIFY status ENUM('pending','applied','cancelled') NOT NULL DEFAULT 'pending'");
        }

        // currency por defecto 'CRC'
        if (Schema::hasColumn('advances', 'currency')) {
            DB::statement("UPDATE advances SET currency = 'CRC' WHERE currency IS NULL OR currency = ''");
        }

        // Índice compuesto
        try {
            DB::statement("CREATE INDEX advances_emp_date_idx ON advances (employee_id, granted_at)");
        } catch (\Throwable $e) {}

        // (Opcional) Drop request_date
        if (Schema::hasColumn('advances', 'request_date')) {
            try {
                DB::statement("ALTER TABLE advances DROP COLUMN request_date");
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // Revert mínimo y seguro en tests
            try {
                DB::statement("DROP INDEX IF EXISTS advances_emp_date_idx");
            } catch (\Throwable $e) {}
            return;
        }

        // MySQL: revert lo que consideres necesario
        try {
            DB::statement("DROP INDEX advances_emp_date_idx ON advances");
        } catch (\Throwable $e) {}

        // Devolver status a VARCHAR (si te interesa)
        try {
            DB::statement("ALTER TABLE advances MODIFY status VARCHAR(255) NOT NULL DEFAULT 'approved'");
        } catch (\Throwable $e) {}

        // (Opcional) Re-crear request_date
        if (!Schema::hasColumn('advances', 'request_date')) {
            try {
                DB::statement("ALTER TABLE advances ADD COLUMN request_date DATE NULL");
                DB::statement("UPDATE advances SET request_date = granted_at WHERE request_date IS NULL");
            } catch (\Throwable $e) {}
        }
    }
};
