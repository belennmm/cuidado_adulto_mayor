<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'is_approved')) {
            return;
        }

        DB::table('users')
            ->where('role', 'admin')
            ->update(['is_approved' => true]);

        DB::table('users')
            ->whereIn('role', ['familiar', 'profesional', 'cuidador_familiar', 'cuidador_profesional'])
            ->update(['is_approved' => false]);
    }

    public function down(): void
    {
        //
    }
};
