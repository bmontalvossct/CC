<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('school_year', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
            $table->unique(['user_id', 'name', 'school_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
    }
};
