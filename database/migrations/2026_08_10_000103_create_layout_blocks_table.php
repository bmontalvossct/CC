<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->unsignedSmallInteger('block_row')->default(1);
            $table->unsignedSmallInteger('block_column')->default(1);
            $table->unsignedSmallInteger('internal_rows');
            $table->unsignedSmallInteger('internal_columns');
            $table->timestamps();
            $table->unique(['section_id', 'block_row', 'block_column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_blocks');
    }
};
