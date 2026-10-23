<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessment_scores', 'marked_absent')) {
            Schema::table('assessment_scores', function (Blueprint $table) {
                $table->boolean('marked_absent')->default(false);
            });
        }
    }

    public function down(): void
    {
        // Preserve saved activity absence markers on rollback.
    }
};
