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
            $table->string('presentation')->nullable()->after('medication_id');
            $table->unsignedInteger('quantity')->default(0)->after('presentation');
            $table->string('unit')->default('unidades')->after('quantity');
            $table->unsignedInteger('minimum_stock')->default(0)->after('unit');
            $table->date('expiration_date')->nullable()->after('minimum_stock');
        });

        DB::table('older_adult_medications')
            ->orderBy('id')
            ->each(function ($assignment) {
                $medication = DB::table('medications')->find($assignment->medication_id);

                if (! $medication) {
                    return;
                }

                DB::table('older_adult_medications')
                    ->where('id', $assignment->id)
                    ->update([
                        'presentation' => $medication->presentation,
                        'quantity' => $medication->quantity,
                        'unit' => $medication->unit,
                        'minimum_stock' => $medication->minimum_stock,
                        'expiration_date' => $medication->expiration_date,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('older_adult_medications', function (Blueprint $table) {
            $table->dropColumn([
                'presentation',
                'quantity',
                'unit',
                'minimum_stock',
                'expiration_date',
            ]);
        });
    }
};
