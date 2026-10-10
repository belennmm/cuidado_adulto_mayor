<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'med_admin_assignment_type_date_unique';

    public function up(): void
    {
        DB::table('medication_administrations')
            ->select('older_adult_medication_id', 'administration_type', 'administration_date')
            ->whereNotNull('older_adult_medication_id')
            ->groupBy('older_adult_medication_id', 'administration_type', 'administration_date')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('older_adult_medication_id')
            ->get()
            ->each(function (object $duplicate): void {
                $duplicateIds = DB::table('medication_administrations')
                    ->where('older_adult_medication_id', $duplicate->older_adult_medication_id)
                    ->where('administration_type', $duplicate->administration_type)
                    ->where('administration_date', $duplicate->administration_date)
                    ->orderBy('id')
                    ->pluck('id')
                    ->slice(1);

                DB::table('medication_administrations')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            });

        Schema::table('medication_administrations', function (Blueprint $table): void {
            $table->unique(
                ['older_adult_medication_id', 'administration_type', 'administration_date'],
                self::INDEX_NAME,
            );
        });
    }

    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
