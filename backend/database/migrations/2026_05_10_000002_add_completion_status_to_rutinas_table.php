<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rutinas', function (Blueprint $table) {
            $table->boolean('completada')->default(false)->after('actividades_completadas');
            $table->timestamp('completada_at')->nullable()->after('completada');
        });
    }

    public function down(): void
    {
        Schema::table('rutinas', function (Blueprint $table) {
            $table->dropColumn(['completada', 'completada_at']);
        });
    }
};
