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
       Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->string('table_name');
    $table->unsignedBigInteger('record_id')->nullable();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('action'); // created|updated|patched|deleted
    $table->json('before_values')->nullable();
    $table->json('after_values')->nullable();
    $table->string('ip')->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamps();
    $table->index(['table_name','record_id']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
