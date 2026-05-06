<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caregiver_schedules', function (Blueprint $table) {
            $table->string('change_request_status')->nullable()->after('notes');
            $table->time('change_request_start_time')->nullable()->after('change_request_status');
            $table->time('change_request_end_time')->nullable()->after('change_request_start_time');
            $table->string('change_request_notes')->nullable()->after('change_request_end_time');
            $table->text('change_request_message')->nullable()->after('change_request_notes');
        });
    }

    public function down(): void
    {
        Schema::table('caregiver_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'change_request_status',
                'change_request_start_time',
                'change_request_end_time',
                'change_request_notes',
                'change_request_message',
            ]);
        });
    }
};
