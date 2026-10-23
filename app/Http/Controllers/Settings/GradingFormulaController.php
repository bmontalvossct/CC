<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGradingFormulaRequest;
use App\Services\GradebookCalculationService;
use App\Services\GradingFormula;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradingFormulaController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/GradingFormula', [
            'formula' => GradingFormula::resolve($request->user()->grading_formula),
            'defaults' => GradingFormula::DEFAULTS,
            'hasCustomFormula' => $request->user()->grading_formula !== null,
        ]);
    }

    public function update(UpdateGradingFormulaRequest $request, GradebookCalculationService $gradebook): RedirectResponse
    {
        $user = $request->user();
        $user->grading_formula = $request->validated();
        $user->save();

        foreach ($user->sections()->pluck('id') as $sectionId) {
            $gradebook->clearGradebookCache((int) $sectionId);
        }

        return to_route('grading-formula.edit')->with('success', 'Grading formula saved. Your gradebooks and analytics now use this formula.');
    }
}
