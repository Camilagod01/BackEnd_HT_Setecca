<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            // 1) ORIGEN de datos: manual (UI) | default (generado)
            if (!Schema::hasColumn('holidays', 'origin')) {
                $table->enum('origin', ['manual','default'])
                      ->default('manual')
                      ->after('scope')
                      ->index();
            }
        });

        // 2) Reemplazar el UNIQUE(date) por UNIQUE(date, scope, origin)
        //    (necesario para permitir coexistir national/company o manual/default el mismo día)
        Schema::table('holidays', function (Blueprint $table) {
            // quitar unique simple en 'date' si existe
            try {
                $table->dropUnique(['date']); // nombre implícito, si falla, usar nombre exacto del índice
            } catch (\Throwable $e) {
                // Si Laravel nombró distinto el índice, intenta por nombre:
                try { $table->dropUnique('holidays_date_unique'); } catch (\Throwable $e) {}
            }

            // agregar compuesto
            $table->unique(['date','scope','origin'], 'holidays_date_scope_origin_unique');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            // volver al unique anterior si quieres un down limpio
            try { $table->dropUnique('holidays_date_scope_origin_unique'); } catch (\Throwable $e) {}

            try { $table->unique('date'); } catch (\Throwable $e) {}

            try { $table->dropColumn('origin'); } catch (\Throwable $e) {}
        });
    }
};
