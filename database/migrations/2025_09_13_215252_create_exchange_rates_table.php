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
       Schema::create('exchange_rates', function (Blueprint $table) {
    $table->id();
    $table->date('rate_date')->index();
    $table->string('base_currency',3)->default('CRC');
    $table->string('quote_currency',3)->default('USD');
    $table->decimal('rate',12,6); // CRC por USD
    $table->string('source')->nullable();
    $table->timestamps();
    $table->unique(['rate_date','base_currency','quote_currency']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
