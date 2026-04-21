<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('older_adult_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('older_adult_id')->constrained('older_adults')->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained('medications')->restrictOnDelete();
            $table->string('dosage')->nullable();
            $table->string('schedule')->nullable();
            $table->json('days')->nullable();
            $table->text('notes')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('older_adult_medications');
    }
};
