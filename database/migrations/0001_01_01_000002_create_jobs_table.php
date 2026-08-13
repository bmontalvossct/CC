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
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasColumns('jobs', ['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])) {
            throw new RuntimeException('The existing jobs table is incomplete; refusing an automatic production repair.');
        }

        if (! Schema::hasIndex('jobs', ['id'], 'primary')) {
            Schema::table('jobs', function (Blueprint $table) {
                $table->primary('id');
            });
        }

        if (! Schema::hasIndex('jobs', ['queue'])) {
            Schema::table('jobs', function (Blueprint $table) {
                $table->index('queue');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasColumns('job_batches', ['id', 'name', 'total_jobs', 'pending_jobs', 'failed_jobs', 'failed_job_ids', 'options', 'cancelled_at', 'created_at', 'finished_at'])) {
            throw new RuntimeException('The existing job_batches table is incomplete; refusing an automatic production repair.');
        }

        if (! Schema::hasIndex('job_batches', ['id'], 'primary')) {
            Schema::table('job_batches', function (Blueprint $table) {
                $table->primary('id');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::hasColumns('failed_jobs', ['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])) {
            throw new RuntimeException('The existing failed_jobs table is incomplete; refusing an automatic production repair.');
        }

        if (! Schema::hasIndex('failed_jobs', ['id'], 'primary')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->primary('id');
            });
        }

        if (! Schema::hasIndex('failed_jobs', ['uuid'], 'unique')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->unique('uuid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
