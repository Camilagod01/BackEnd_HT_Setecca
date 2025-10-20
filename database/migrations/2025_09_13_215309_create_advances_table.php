<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->enum('currency', ['CRC', 'USD'])->default('CRC');
            $table->date('granted_at');

            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'applied', 'cancelled'])->default('pending');

            $table->json('scheduling_json')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'granted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advances');
    }
};
