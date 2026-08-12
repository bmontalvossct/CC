<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedInteger('duration_minutes');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'session_date', 'starts_at'], 'attendance_session_start_unique');
            $table->index(['section_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
