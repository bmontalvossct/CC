<?php

namespace App\Http\Controllers\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Section;
use App\Models\Student;
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

    private function render(Section $section, bool $print): Response
    {
        $this->authorizeSection($section);
        [$assessments, $students, $scores] = $this->data($section);
        $rows = $students->map(function ($student) use ($assessments, $scores) {
            $studentScores = $scores->get($student->id, collect())->keyBy('assessment_id');
            $categories = collect(Assessment::TYPES)->mapWithKeys(function ($type) use ($assessments, $studentScores) {
                $items = $assessments->where('type', $type);
                $earned = $items->sum(fn ($item) => (float) ($studentScores->get($item->id)?->score ?? 0));
                $possible = $items->sum(fn ($item) => (float) $item->max_points);

                return [$type => [
                    'earned' => round($earned, 2),
                    'possible' => round($possible, 2),
                    'percentage' => $possible > 0 ? round($earned / $possible * 100, 2) : null,
                    'missing' => $items->filter(fn ($item) => $studentScores->get($item->id)?->score === null)->count(),
                ]];
            });

            return [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'scores' => $assessments->mapWithKeys(fn ($item) => [$item->id => $studentScores->get($item->id)?->score]),
                'categories' => $categories,
            ];
        });
        $categorySummary = collect(Assessment::TYPES)->mapWithKeys(fn ($type) => [$type => [
            'count' => $assessments->where('type', $type)->count(),
            'possible' => round($assessments->where('type', $type)->sum('max_points'), 2),
        ]]);

        return Inertia::render('reports/Gradebook', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'assessments' => $assessments,
            'rows' => $rows,
            'categorySummary' => $categorySummary,
            'printMode' => $print,
        ]);
    }

    /** @return array{Collection, Collection, Collection} */
    private function data(Section $section): array
    {
        $assessments = Assessment::where('section_id', $section->id)->orderBy('conducted_on')->orderBy('id')
            ->get(['id', 'type', 'title', 'conducted_on', 'max_points']);
        $students = Student::where('section_id', $section->id)->orderBy('last_name')->orderBy('first_name')->get();
        $scores = AssessmentScore::whereIn('assessment_id', $assessments->pluck('id'))->get()->groupBy('student_id');

        return [$assessments, $students, $scores];
    }
}
