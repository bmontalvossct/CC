<?php

namespace App\Http\Controllers;

use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RecitationController extends Controller
{
    public function index(Section $section): Response
    {
        Gate::authorize('update', $section);

        // Fetch students in this section with their seat labels
        $students = Student::query()
            ->where('students.section_id', $section->id)
            ->where('students.is_active', true)
            ->leftJoin('seats', 'seats.student_id', '=', 'students.id')
            ->leftJoin('layout_blocks', 'layout_blocks.id', '=', 'seats.layout_block_id')
            ->orderByRaw('CASE WHEN seats.id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('layout_blocks.block_row')
            ->orderBy('layout_blocks.block_column')
            ->orderBy('seats.row_number')
            ->orderBy('seats.column_number')
            ->orderBy('students.last_name')
            ->get([
                'students.id', 'students.student_number', 'students.first_name', 'students.middle_name',
                'students.last_name', 'students.photo_path', 'seats.label as seat_label'
            ]);

        // Map full name
        $students = $students->map(function ($s) {
            $s->full_name = trim("{$s->last_name}, {$s->first_name} {$s->middle_name}");
            $s->photo_url = $s->photo_path ? asset('storage/' . $s->photo_path) : null;
            return $s;
        });

        // Get recitations for today
        $today = now()->toDateString();
        $todayRecitations = Recitation::query()
            ->where('section_id', $section->id)
            ->whereDate('conducted_on', $today)
            ->get()
            ->keyBy('student_id');

        // Get all recitations for computing overall grades
        $allRecitations = Recitation::query()
            ->where('section_id', $section->id)
            ->get()
            ->groupBy('student_id');

        // Compute grades and summaries
        $rubricSummary = $students->map(function ($student) use ($allRecitations, $todayRecitations) {
            $studentRecs = $allRecitations->get($student->id, collect());
            $todayRec = $todayRecitations->get($student->id);

            $count = $studentRecs->count();
            $avgAccuracy = $count ? round($studentRecs->avg('accuracy'), 2) : null;
            $avgDelivery = $count ? round($studentRecs->avg('delivery'), 2) : null;
            $avgScore = $count ? round($studentRecs->avg('score'), 2) : null;
            // Computing grade: (Average score / 10) * 100
            $computedGrade = $avgScore !== null ? round(($avgScore / 10) * 100, 2) : null;

            return [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'seat_label' => $student->seat_label,
                'times_called' => $count,
                'avg_accuracy' => $avgAccuracy,
                'avg_delivery' => $avgDelivery,
                'avg_score' => $avgScore,
                'computed_grade' => $computedGrade,
                'today_recitation' => $todayRec,
                'called_today' => $todayRec !== null,
            ];
        })->values();

        // Load section with layout blocks
        $section->load(['layoutBlocks.seats' => function ($query) {
            $query->orderBy('row_number')->orderBy('column_number');
        }]);

        return Inertia::render('sections/OralParticipation', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title', 'layoutBlocks'),
            'students' => $rubricSummary,
            'todayDate' => now()->format('Y-m-d'),
            'todayFormatted' => now()->format('M d, Y'),
        ]);
    }

    public function storeScore(Request $request, Section $section, Student $student): RedirectResponse
    {
        Gate::authorize('update', $section);
        abort_unless($student->section_id === $section->id, 404);

        $data = $request->validate([
            'accuracy' => ['required', 'integer', 'min:1', 'max:5'],
            'delivery' => ['required', 'integer', 'min:1', 'max:5'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $score = $data['accuracy'] + $data['delivery'];
        $today = now()->toDateString();

        Recitation::updateOrCreate(
            [
                'section_id' => $section->id,
                'student_id' => $student->id,
                'conducted_on' => $today,
            ],
            [
                'accuracy' => $data['accuracy'],
                'delivery' => $data['delivery'],
                'score' => $score,
                'comments' => $data['comments'] ?? null,
            ]
        );

        return back()->with('success', "Recitation grade recorded for {$student->full_name}.");
    }
}
