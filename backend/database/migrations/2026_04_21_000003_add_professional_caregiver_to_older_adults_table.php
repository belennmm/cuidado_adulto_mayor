<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('older_adults', function (Blueprint $table) {
            $table->foreignId('professional_caregiver_id')
                ->nullable()
                ->after('caregiver_family')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('older_adults', function (Blueprint $table) {
            $table->dropConstrainedForeignId('professional_caregiver_id');
        });
    }
};
