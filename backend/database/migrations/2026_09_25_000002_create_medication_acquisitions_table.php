<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_acquisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained()->restrictOnDelete();
            $table->foreignId('older_adult_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('acquired_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_acquisitions');
    }
};
