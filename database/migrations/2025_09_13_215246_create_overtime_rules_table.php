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
      Schema::create('overtime_rules', function (Blueprint $table) {
    $table->id();
    $table->enum('rule_type',['daily','weekday','weekend','holiday'])->default('daily');
    $table->string('condition')->nullable(); // e.g. ">=8h", "sunday"
    $table->decimal('multiplier',4,2);       // 1.50, 2.00
    $table->boolean('active')->default(true);
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_rules');
    }
};
