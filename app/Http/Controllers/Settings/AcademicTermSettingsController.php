<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicTermSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $currentTerm = $user->currentAcademicTerm();

        $terms = AcademicTerm::query()
            ->where('user_id', $user->id)
            ->withCount('sections')
            ->orderByDesc('is_current')
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (AcademicTerm $term) => [
                'id' => $term->id,
                'name' => $term->name,
                'school_year' => $term->school_year,
                'starts_on' => $term->starts_on->format('Y-m-d'),
                'ends_on' => $term->ends_on->format('Y-m-d'),
                'is_current' => $term->is_current,
                'default_starts_at' => $term->default_starts_at ?? '08:00',
                'default_ends_at' => $term->default_ends_at ?? '09:30',
                'sections_count' => $term->sections_count,
            ]);

        return Inertia::render('settings/AcademicTerm', [
            'currentTerm' => [
                'id' => $currentTerm->id,
                'name' => $currentTerm->name,
                'school_year' => $currentTerm->school_year,
                'starts_on' => $currentTerm->starts_on->format('Y-m-d'),
                'ends_on' => $currentTerm->ends_on->format('Y-m-d'),
                'is_current' => $currentTerm->is_current,
                'default_starts_at' => $currentTerm->default_starts_at ?? '08:00',
                'default_ends_at' => $currentTerm->default_ends_at ?? '09:30',
            ],
            'terms' => $terms,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'school_year' => ['required', 'string', 'max:20'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'default_starts_at' => ['nullable', 'date_format:H:i'],
            'default_ends_at' => ['nullable', 'date_format:H:i', 'after:default_starts_at'],
        ]);

        $term = AcademicTerm::resolveForUser($user->id, [
            'name' => $data['name'],
            'school_year' => $data['school_year'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'default_starts_at' => $data['default_starts_at'] ?? '08:00',
            'default_ends_at' => $data['default_ends_at'] ?? '09:30',
            'is_current' => true,
        ]);

        return back()->with('success', "Universal semester schedule saved ({$term->name} SY {$term->school_year}).");
    }

    public function makeCurrent(Request $request, AcademicTerm $term): RedirectResponse
    {
        if ($term->user_id !== $request->user()->id) {
            abort(403);
        }

        $term->makeCurrent();

        return back()->with('success', "Active semester set to {$term->name} SY {$term->school_year}.");
    }
}
