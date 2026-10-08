<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->unique(
                ['older_adult_medication_id', 'administration_type', 'administration_date'],
                'med_admin_assignment_type_date_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropUnique('med_admin_assignment_type_date_unique');
        });
    }
};
