<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('assessments', 'term_period')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->string('term_period', 20)->nullable()->after('type'); // 'midterm', 'final', or null
            });
        }

        if (! Schema::hasColumn('projects', 'term_period')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('term_period', 20)->nullable()->after('type'); // 'midterm', 'final', or null
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('term_period');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('term_period');
        });
    }
};
