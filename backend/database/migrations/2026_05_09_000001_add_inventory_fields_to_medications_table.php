<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->string('presentation')->nullable()->after('name');
            $table->unsignedInteger('quantity')->default(0)->after('presentation');
            $table->string('unit')->default('unidades')->after('quantity');
            $table->unsignedInteger('minimum_stock')->default(0)->after('unit');
            $table->date('expiration_date')->nullable()->after('minimum_stock');
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
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
