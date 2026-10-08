<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('older_adult_medications', function (Blueprint $table) {
            $table->foreignId('older_adult_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('older_adult_medications')->whereNull('older_adult_id')->exists()) {
            throw new RuntimeException('Reasigna el stock sin asignar antes de revertir esta migración.');
        }

        Schema::table('older_adult_medications', function (Blueprint $table) {
            $table->foreignId('older_adult_id')->nullable(false)->change();
        });
    }
};
