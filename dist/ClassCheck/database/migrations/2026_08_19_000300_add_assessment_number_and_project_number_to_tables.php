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
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('assessment_number', 50)->nullable()->after('type');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('project_number', 50)->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('assessment_number');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('project_number');
        });
    }
};
