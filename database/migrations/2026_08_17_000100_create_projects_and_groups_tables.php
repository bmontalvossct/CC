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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->string('type')->default('project'); // 'project' or 'reporting'
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('conducted_on')->nullable();
            $table->decimal('max_points', 8, 2)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->timestamps();

            $table->index(['section_id', 'conducted_on']);
        });

        Schema::create('project_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedInteger('group_number')->default(1);
            $table->string('name')->default('Group 1');
            $table->text('topic')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'group_number']);
        });

        Schema::create('project_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_group_id')->constrained('project_groups')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_group_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_group_members');
        Schema::dropIfExists('project_groups');
        Schema::dropIfExists('projects');
    }
};
