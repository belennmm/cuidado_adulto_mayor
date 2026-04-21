<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('older_adults', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->unsignedSmallInteger('age')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('gender')->nullable();
            $table->string('room')->nullable();
            $table->string('status')->default('Estable');
            $table->string('caregiver_family')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('allergies')->nullable();
            $table->text('medical_history')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('older_adults');
    }
};