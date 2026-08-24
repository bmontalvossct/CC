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
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasColumns('cache', ['key', 'value', 'expiration'])) {
            throw new RuntimeException('The existing cache table is incomplete; refusing an automatic production repair.');
        }

        if (! Schema::hasIndex('cache', ['key'], 'primary')) {
            Schema::table('cache', function (Blueprint $table) {
                $table->primary('key');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasColumns('cache_locks', ['key', 'owner', 'expiration'])) {
            throw new RuntimeException('The existing cache_locks table is incomplete; refusing an automatic production repair.');
        }

        if (! Schema::hasIndex('cache_locks', ['key'], 'primary')) {
            Schema::table('cache_locks', function (Blueprint $table) {
                $table->primary('key');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
