<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->foreignId('older_adult_id')
                ->nullable()
                ->after('adult_name')
                ->constrained('older_adults')
                ->nullOnDelete();
        });

        DB::table('incidents')
            ->whereNotNull('adult_name')
            ->orderBy('id')
            ->get()
            ->each(function ($incident) {
                $olderAdultId = DB::table('older_adults')
                    ->whereRaw('LOWER(full_name) = ?', [strtolower((string) $incident->adult_name)])
                    ->value('id');

                if (!$olderAdultId) {
                    return;
                }

                DB::table('incidents')
                    ->where('id', $incident->id)
                    ->update(['older_adult_id' => $olderAdultId]);
            });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('older_adult_id');
        });
    }
};
