<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            // Agregar columna amount si no existe
            if (!Schema::hasColumn('loan_payments', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0);
            }

            // Si existen columnas viejas, las mantenemos por compatibilidad
            // pero puedes comentarlas si ya no se usan.
            if (!Schema::hasColumn('loan_payments', 'status')) {
                DB::statement("ALTER TABLE loan_payments ADD COLUMN `status` ENUM('pending','paid','skipped') NOT NULL DEFAULT 'pending'");
            }

            if (!Schema::hasColumn('loan_payments', 'source')) {
                DB::statement("ALTER TABLE loan_payments ADD COLUMN `source` ENUM('payroll','manual') NOT NULL DEFAULT 'payroll'");
            }

            if (!Schema::hasColumn('loan_payments', 'remarks')) {
                $table->text('remarks')->nullable();
            }

            // Asegurar due_date existe y tiene índice
            if (!Schema::hasColumn('loan_payments', 'due_date')) {
                $table->date('due_date')->nullable()->index();
            }

            // timestamps si faltan
            if (!Schema::hasColumn('loan_payments', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // No eliminamos columnas para evitar pérdida de datos
    }
};
