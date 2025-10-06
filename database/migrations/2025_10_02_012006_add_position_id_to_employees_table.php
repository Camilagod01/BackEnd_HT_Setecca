<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
             $table->foreignId('position_id')
              ->nullable()
              ->constrained('positions')
              ->nullOnDelete()
              ->after('status'); // Ajusta la columna según tu estructura
        });
    }

    
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
             $table->dropConstrainedForeignId('position_id');
        });
    }
};
