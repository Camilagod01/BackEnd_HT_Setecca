<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // PASO 1: agregar columnas básicas con Schema (sin posiciones)
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
                $table->index(['employee_id']);
            }

            if (!Schema::hasColumn('loans', 'amount')) {
                $table->decimal('amount', 12, 2)->nullable();
            }

            if (!Schema::hasColumn('loans', 'granted_at')) {
                $table->date('granted_at')->nullable();
            }

            if (!Schema::hasColumn('loans', 'notes')) {
                $table->text('notes')->nullable();
            }

            if (!Schema::hasColumn('loans', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if (!Schema::hasColumn('loans', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }

            if (!Schema::hasColumn('loans', 'created_at')) {
                $table->timestamps(); // agrega created_at/updated_at si faltan
            }
        });

        // PASO 2: columnas ENUM con SQL, SIN "AFTER"
        if (!Schema::hasColumn('loans', 'currency')) {
            DB::statement("ALTER TABLE loans ADD COLUMN `currency` ENUM('CRC','USD') NOT NULL DEFAULT 'CRC'");
        }
        if (!Schema::hasColumn('loans', 'status')) {
            DB::statement("ALTER TABLE loans ADD COLUMN `status` ENUM('active','closed') NOT NULL DEFAULT 'active'");
        }

        // PASO 3: rellenar nulos razonables y forzar NOT NULL donde aplica
        // granted_at
        if (Schema::hasColumn('loans', 'granted_at')) {
            DB::statement("UPDATE loans SET granted_at = CURDATE() WHERE granted_at IS NULL");
            DB::statement("ALTER TABLE loans MODIFY granted_at DATE NOT NULL");
        }
        // amount
        if (Schema::hasColumn('loans', 'amount')) {
            DB::statement("UPDATE loans SET amount = 0 WHERE amount IS NULL");
            DB::statement("ALTER TABLE loans MODIFY amount DECIMAL(12,2) NOT NULL");
        }
        // employee_id (si existe la col, asegurar no nula cuando sea posible)
        if (Schema::hasColumn('loans', 'employee_id')) {
            // si tienes datos sin employee_id, déjalo nullable; si no, puedes forzarlo NOT NULL:
            // DB::statement("ALTER TABLE loans MODIFY employee_id BIGINT UNSIGNED NOT NULL");
        }

        // PASO 4: índice compuesto
        try {
            DB::statement("CREATE INDEX loans_emp_date_idx ON loans (employee_id, granted_at)");
        } catch (\Throwable $e) {
            // ya existe
        }
    }

    public function down(): void
    {
        // Reversa mínima (no borrar datos)
        try { DB::statement("DROP INDEX loans_emp_date_idx ON loans"); } catch (\Throwable $e) {}
        // No eliminamos columnas para preservar información.
    }
};
