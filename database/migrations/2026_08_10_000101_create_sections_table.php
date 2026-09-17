<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('subject_code', 50);
            $table->string('subject_title');
            $table->string('name');
            $table->string('room')->nullable();
            $table->string('enrollment_token', 64)->unique();
            $table->boolean('enrollment_open')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
