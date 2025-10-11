<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            // si ya es nullable no falla; si no, lo cambia
            $table->decimal('base_hourly_rate', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            // vuelve a NOT NULL con 0.00 para no romper (opcional)
            $table->decimal('base_hourly_rate', 12, 2)->default(0)->change();
        });
    }
};

