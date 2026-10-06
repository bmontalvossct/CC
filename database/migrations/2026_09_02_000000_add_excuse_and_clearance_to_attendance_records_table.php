<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('excuse_reason', 255)->nullable()->after('attended_minutes');
            $table->boolean('points_awarded')->default(false)->after('excuse_reason');
            $table->boolean('cleared_by_letter')->default(false)->after('points_awarded');
            $table->timestamp('cleared_at')->nullable()->after('cleared_by_letter');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['excuse_reason', 'points_awarded', 'cleared_by_letter', 'cleared_at']);
        });
    }
};
