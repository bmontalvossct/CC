<?php

use App\Models\Section;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // A one-off hosted dataset repair is not applicable to installed classrooms.
        if (config('app.offline')) {
            return;
        }
        $section = Section::where('name', 'like', '%BSICT 4C%')->first();
        if (! $section) {
            return;
        }

        foreach ($section->students as $student) {
            $currentFirst = $student->first_name;
            $currentLast = $student->last_name;

            $student->update([
                'first_name' => $currentLast,
                'last_name' => $currentFirst,
            ]);
        }
    }

    public function down(): void
    {
        $section = Section::where('name', 'like', '%BSICT 4C%')->first();
        if (! $section) {
            return;
        }

        foreach ($section->students as $student) {
            $student->update([
                'first_name' => $student->last_name,
                'last_name' => $student->first_name,
            ]);
        }
    }
};
