<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->boolean('is_current')->default(false)->after('ends_on');
            $table->string('default_starts_at', 10)->nullable()->default('08:00')->after('is_current');
            $table->string('default_ends_at', 10)->nullable()->default('09:30')->after('default_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropColumn(['is_current', 'default_starts_at', 'default_ends_at']);
        });
    }
};
