<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('students')) {
            $students = DB::table('students')->get();
            foreach ($students as $student) {
                DB::table('students')->where('id', $student->id)->update([
                    'first_name' => mb_convert_encoding((string) $student->first_name, 'UTF-8', 'UTF-8'),
                    'last_name' => mb_convert_encoding((string) $student->last_name, 'UTF-8', 'UTF-8'),
                    'middle_name' => $student->middle_name !== null ? mb_convert_encoding((string) $student->middle_name, 'UTF-8', 'UTF-8') : null,
                    'student_number' => $student->student_number !== null ? mb_convert_encoding((string) $student->student_number, 'UTF-8', 'UTF-8') : null,
                ]);
            }
        }

        if (Schema::hasTable('sections')) {
            $sections = DB::table('sections')->get();
            foreach ($sections as $section) {
                DB::table('sections')->where('id', $section->id)->update([
                    'subject_code' => mb_convert_encoding((string) $section->subject_code, 'UTF-8', 'UTF-8'),
                    'subject_title' => mb_convert_encoding((string) $section->subject_title, 'UTF-8', 'UTF-8'),
                    'name' => mb_convert_encoding((string) $section->name, 'UTF-8', 'UTF-8'),
                    'room' => $section->room !== null ? mb_convert_encoding((string) $section->room, 'UTF-8', 'UTF-8') : null,
                ]);
            }
        }

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->truncate();
        }
    }

    public function down(): void {}
};
