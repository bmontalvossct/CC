<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'grading_formula')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('grading_formula')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Preserve saved grading formulas when rolling back; no user data is removed.
    }
};
