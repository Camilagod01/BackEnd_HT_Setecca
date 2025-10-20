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
                DB::statement("ALTER TABLE loans MODIFY principal DECIMAL(12,2) NOT NULL DEFAULT 0");
            } else {
                $table->decimal('principal', 12, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Si lo necesita la tabla
            DB::statement("ALTER TABLE loans MODIFY principal DECIMAL(12,2) NOT NULL");
        });
    }
};
