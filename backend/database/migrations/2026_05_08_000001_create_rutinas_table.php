<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rutinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('older_adult_id')->constrained('older_adults')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre');
            $table->string('horario');
            $table->json('actividades');
            $table->timestamps();

            $table->index(['older_adult_id', 'horario']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rutinas');
    }
};
