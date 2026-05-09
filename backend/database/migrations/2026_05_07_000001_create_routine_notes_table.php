<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('older_adult_id')->constrained('older_adults')->cascadeOnDelete();
            $table->foreignId('professional_caregiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->date('note_date');
            $table->timestamps();

            $table->index(['older_adult_id', 'note_date']);
            $table->index(['professional_caregiver_id', 'note_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_notes');
    }
};
