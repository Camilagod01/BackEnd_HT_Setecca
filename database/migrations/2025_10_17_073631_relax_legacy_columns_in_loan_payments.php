<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Si existen columnas legadas, las relajamos para que no bloqueen inserts modernos
        if (Schema::hasColumn('loan_payments', 'amount_due')) {
            // permitir NULL y default 0
            DB::statement("ALTER TABLE loan_payments MODIFY amount_due DECIMAL(12,2) NULL DEFAULT 0");
        }
        if (Schema::hasColumn('loan_payments', 'amount_paid')) {
            DB::statement("ALTER TABLE loan_payments MODIFY amount_paid DECIMAL(12,2) NULL DEFAULT 0");
        }
        if (Schema::hasColumn('loan_payments', 'paid_at')) {
            // por si alguna vez quedó NOT NULL
            DB::statement("ALTER TABLE loan_payments MODIFY paid_at DATE NULL");
        }
        if (Schema::hasColumn('loan_payments', 'applied')) {
            // boolean con default 0
            DB::statement("ALTER TABLE loan_payments MODIFY applied TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (Schema::hasColumn('loan_payments', 'payroll_cycle_date')) {
            DB::statement("ALTER TABLE loan_payments MODIFY payroll_cycle_date DATE NULL");
        }
    }

    public function down(): void
    {
        // no revertimos para no romper compatibilidad; si lo necesitaramos, aquí podrías volver a los tipos previos
    }
};
