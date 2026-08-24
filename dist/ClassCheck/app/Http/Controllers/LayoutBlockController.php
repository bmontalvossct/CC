<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveFloorPlanRequest;
use App\Http\Requests\StoreLayoutBlockRequest;
use App\Models\LayoutBlock;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LayoutBlockController extends Controller
{
    public function replace(SaveFloorPlanRequest $request, Section $section): RedirectResponse
    {
        $unseated = DB::transaction(function () use ($request, $section): int {
            $oldBlocks = $section->layoutBlocks()
                ->with(['seats' => fn ($query) => $query->whereNotNull('student_id')->orderBy('row_number')->orderBy('column_number')])
                ->orderBy('block_row')
                ->orderBy('block_column')
                ->get();
            $preserveCoordinates = $oldBlocks->count() === 1;
            $assignments = $oldBlocks
                ->flatMap(fn (LayoutBlock $block) => $block->seats->map(fn ($seat) => [
                    'student_id' => $seat->student_id,
                    'row_number' => $seat->row_number,
                    'column_number' => $seat->column_number,
                ]))
                ->unique('student_id')
                ->values();

            $section->layoutBlocks()->delete();

            $data = $request->validated();
            $block = $section->layoutBlocks()->create([
                'label' => 'Classroom',
                'block_row' => 1,
                'block_column' => 1,
                'internal_rows' => $data['rows'],
                'internal_columns' => $data['columns'],
                'aisle_after_rows' => array_values($data['aisle_after_rows']),
                'aisle_after_columns' => array_values($data['aisle_after_columns']),
            ]);

            $seatData = [];
            for ($row = 1; $row <= $data['rows']; $row++) {
                for ($column = 1; $column <= $data['columns']; $column++) {
                    $seatData[] = [
                        'row_number' => $row,
                        'column_number' => $column,
                        'label' => "R{$row}-C{$column}",
                        'is_disabled' => false,
                    ];
                }
            }

            $seats = $block->seats()->createMany($seatData);
            $seatsByCoordinate = $seats->keyBy(fn ($seat) => "{$seat->row_number}:{$seat->column_number}");
            $assignedStudentIds = collect();

            // 1. Try to preserve coordinates if we came from a single block layout
            if ($preserveCoordinates) {
                foreach ($assignments as $assignment) {
                    $target = $seatsByCoordinate->get("{$assignment['row_number']}:{$assignment['column_number']}");
                    if ($target && ! $target->student_id) {
                        $target->update(['student_id' => $assignment['student_id']]);
                        $assignedStudentIds->push($assignment['student_id']);
                    }
                }
            }

            // 2. For any students that couldn't be mapped by coordinate (or if we had multiple blocks),
            // fill them into the remaining empty seats sequentially.
            $unassigned = $assignments->filter(fn ($a) => ! $assignedStudentIds->contains($a['student_id']))->values();
            if ($unassigned->isNotEmpty()) {
                $emptySeats = $block->seats()
                    ->whereNull('student_id')
                    ->where('is_disabled', false)
                    ->orderBy('row_number')
                    ->orderBy('column_number')
                    ->get();

                foreach ($unassigned as $index => $assignment) {
                    if ($emptySeats->has($index)) {
                        $target = $emptySeats->get($index);
                        $target->update(['student_id' => $assignment['student_id']]);
                        $assignedStudentIds->push($assignment['student_id']);
                    }
                }
            }

            return max(0, $assignments->count() - $assignedStudentIds->count());
        });

        $message = 'Classroom floor updated.';
        if ($unseated > 0) {
            $message .= " {$unseated} student".($unseated === 1 ? ' is' : 's are').' now unseated because the new room has fewer chairs.';
        }

        return back()->with('success', $message);
    }

    public function store(StoreLayoutBlockRequest $request, Section $section): RedirectResponse
    {
        DB::transaction(function () use ($request, $section) {
            $data = $request->validated();
            $block = $section->layoutBlocks()->create(collect($data)->except('disabled_positions')->all());
            $disabled = array_flip($data['disabled_positions'] ?? []);
            $seats = [];
            for ($row = 1; $row <= $block->internal_rows; $row++) {
                for ($column = 1; $column <= $block->internal_columns; $column++) {
                    $seats[] = [
                        'row_number' => $row,
                        'column_number' => $column,
                        'label' => "{$block->label}-R{$row}-C{$column}",
                        'is_disabled' => isset($disabled["{$row}:{$column}"]),
                    ];
                }
            }
            $block->seats()->createMany($seats);
        });

        return back()->with('success', 'Chair block added to the classroom.');
    }

    public function destroy(Section $section, LayoutBlock $layoutBlock): RedirectResponse
    {
        Gate::authorize('update', $section);
        abort_unless($layoutBlock->section_id === $section->id, 404);
        if ($layoutBlock->seats()->whereNotNull('student_id')->exists()) {
            throw ValidationException::withMessages(['layout' => 'Move students out of this block before removing it.']);
        }
        $layoutBlock->delete();

        return back()->with('success', 'Chair block removed.');
    }
}
