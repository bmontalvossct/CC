<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('section_schedules', function (Blueprint $table) {
            $table->string('room')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('section_schedules', function (Blueprint $table) {
            $table->dropColumn('room');
        });
    }
};
