<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('older_adult_id')->constrained('older_adults')->cascadeOnDelete();
            $table->foreignId('older_adult_medication_id')->nullable()->constrained('older_adult_medications')->nullOnDelete();
            $table->foreignId('medication_id')->constrained('medications')->restrictOnDelete();
            $table->string('administration_type')->default('scheduled');
            $table->string('dosage')->nullable();
            $table->date('administration_date');
            $table->time('administration_time');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
    }
};
