<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layout_blocks', function (Blueprint $table) {
            $table->json('aisle_after_rows')->nullable()->after('internal_columns');
            $table->json('aisle_after_columns')->nullable()->after('aisle_after_rows');
        });
    }

    public function down(): void
    {
        Schema::table('layout_blocks', function (Blueprint $table) {
            $table->dropColumn(['aisle_after_rows', 'aisle_after_columns']);
        });
    }
};
