<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('older_adults', function (Blueprint $table) {
            $table->foreignId('family_caregiver_id')
                ->nullable()
                ->after('caregiver_family')
                ->constrained('users')
                ->nullOnDelete();
        });

        $familyCaregivers = User::query()
            ->where('role', 'familiar')
            ->where('is_approved', true)
            ->get()
            ->keyBy(fn (User $user) => Str::lower((string) $user->name));

        DB::table('older_adults')
            ->whereNotNull('caregiver_family')
            ->orderBy('id')
            ->get()
            ->each(function ($olderAdult) use ($familyCaregivers) {
                $caregiver = $familyCaregivers->get(Str::lower((string) $olderAdult->caregiver_family));

                if (!$caregiver) {
                    return;
                }

                DB::table('older_adults')
                    ->where('id', $olderAdult->id)
                    ->update([
                        'family_caregiver_id' => $caregiver->id,
                        'caregiver_family' => $caregiver->name,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('older_adults', function (Blueprint $table) {
            $table->dropConstrainedForeignId('family_caregiver_id');
        });
    }
};
