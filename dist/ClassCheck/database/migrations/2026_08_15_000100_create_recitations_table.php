<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('conducted_on');
            $table->unsignedInteger('accuracy')->nullable();
            $table->unsignedInteger('delivery')->nullable();
            $table->decimal('score', 10, 2);
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'student_id', 'conducted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recitations');
    }
};
