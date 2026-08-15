<?php

namespace App\Http\Controllers\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentReportController extends AssessmentModuleController
{
    public function gradebook(Section $section): Response
    {
        return $this->render($section, false);
    }

    public function print(Section $section): Response
    {
        return $this->render($section, true);
    }

    public function updateWeights(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($section);
        $data = $request->validate([
            'activity' => ['required', 'integer', 'min:0', 'max:100'],
            'quiz' => ['required', 'integer', 'min:0', 'max:100'],
            'exam' => ['required', 'integer', 'min:0', 'max:100'],
            'recitation' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $total = $data['activity'] + $data['quiz'] + $data['exam'] + $data['recitation'];
        if ($total !== 100) {
            return back()->withErrors(['weights' => 'Component weights must total exactly 100%.']);
        }

        $section->update(['grading_weights' => $data]);

        return back()->with('success', 'Grading weights saved.');
    }

    private function render(Section $section, bool $print): Response
    {
        $this->authorizeSection($section);
        [$assessments, $students, $scores] = $this->data($section);
        $categorySummary = collect(Assessment::TYPES)->mapWithKeys(fn ($type) => [$type => [
            'count' => 0,
            'possible' => 0.0,
        ]]);

        foreach ($assessments as $assessment) {
            $categorySummary[$assessment->type] = [
                'count' => $categorySummary[$assessment->type]['count'] + 1,
                'possible' => round($categorySummary[$assessment->type]['possible'] + (float) $assessment->max_points, 2),
            ];
        }

        // Fetch recitation data per student
        $recitations = Recitation::where('section_id', $section->id)
            ->get(['student_id', 'score'])
            ->groupBy('student_id');

        $rows = $students->map(function ($student) use ($assessments, $scores, $categorySummary, $recitations) {
            $studentScores = $scores->get($student->id, collect());
            $scoreGrid = [];
            $earnedByType = array_fill_keys(Assessment::TYPES, 0.0);
            $missingByType = array_fill_keys(Assessment::TYPES, 0);

            foreach ($assessments as $assessment) {
                $score = $studentScores->get($assessment->id)?->score;
                $scoreGrid[$assessment->id] = $score;
                $earnedByType[$assessment->type] += (float) ($score ?? 0);

                if ($score === null) {
                    $missingByType[$assessment->type]++;
                }
            }

            $categories = collect(Assessment::TYPES)->mapWithKeys(function ($type) use ($categorySummary, $earnedByType, $missingByType) {
                $earned = round($earnedByType[$type], 2);
                $possible = $categorySummary[$type]['possible'];

                return [$type => [
                    'earned' => $earned,
                    'possible' => $possible,
                    'percentage' => $possible > 0 ? round($earned / $possible * 100, 2) : null,
                    'missing' => $missingByType[$type],
                ]];
            });

            // Recitation stats: average out of 10, then percentage
            $studentRecs = $recitations->get($student->id, collect());
            $recitationCount = $studentRecs->count();
            $recitationAvg = $recitationCount > 0 ? round($studentRecs->avg('score'), 2) : null;
            $recitationPct = $recitationAvg !== null ? round(($recitationAvg / 10) * 100, 2) : null;

            return [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'scores' => $scoreGrid,
                'categories' => $categories,
                'recitation' => [
                    'count' => $recitationCount,
                    'avg_score' => $recitationAvg,
                    'percentage' => $recitationPct,
                ],
            ];
        });

        $defaultWeights = ['activity' => 25, 'quiz' => 25, 'exam' => 30, 'recitation' => 20];
        $gradingWeights = $section->grading_weights ?? $defaultWeights;

        return Inertia::render('reports/Gradebook', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'assessments' => $assessments,
            'rows' => $rows,
            'categorySummary' => $categorySummary,
            'gradingWeights' => $gradingWeights,
            'printMode' => $print,
        ]);
    }

    /** @return array{Collection, Collection, Collection} */
    private function data(Section $section): array
    {
        $assessments = Assessment::where('section_id', $section->id)->orderBy('conducted_on')->orderBy('id')
            ->get(['id', 'type', 'title', 'conducted_on', 'max_points']);
        $students = Student::where('section_id', $section->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'student_number', 'first_name', 'middle_name', 'last_name']);
        $scores = AssessmentScore::whereIn('assessment_id', $assessments->pluck('id'))
            ->get(['assessment_id', 'student_id', 'score'])
            ->groupBy('student_id')
            ->map->keyBy('assessment_id');

        return [$assessments, $students, $scores];
    }
}
