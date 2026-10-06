<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('rubric_type', 30)->nullable()->after('max_points');
            $table->json('rubric_data')->nullable()->after('rubric_type');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('rubric_type', 30)->nullable()->after('max_points');
            $table->json('rubric_data')->nullable()->after('rubric_type');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['rubric_type', 'rubric_data']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['rubric_type', 'rubric_data']);
        });
    }
};
