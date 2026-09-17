<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('conducted_on');
            $table->decimal('max_points', 10, 2);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->timestamps();

            $table->index(['section_id', 'type', 'conducted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
