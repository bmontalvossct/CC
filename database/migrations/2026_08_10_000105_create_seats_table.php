<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layout_block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('row_number');
            $table->unsignedSmallInteger('column_number');
            $table->string('label', 80);
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
            $table->unique(['layout_block_id', 'row_number', 'column_number']);
            $table->unique('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
