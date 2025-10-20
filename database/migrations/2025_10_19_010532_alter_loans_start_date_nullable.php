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
            DB::statement("ALTER TABLE loans MODIFY start_date DATE NULL");
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
