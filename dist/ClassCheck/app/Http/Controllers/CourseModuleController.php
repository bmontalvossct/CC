<?php

namespace App\Http\Controllers;

use App\Models\CourseModule;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseModuleController extends Controller
{
    public function index(Request $request, Section $section): Response
    {
        $this->authorizeSection($request, $section);
        $section->load(['academicTerm']);

        $modules = $section->courseModules()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CourseModule $module) => [
                'id' => $module->id,
                'section_id' => $module->section_id,
                'module_number' => $module->module_number,
                'title' => $module->title,
                'description' => $module->description,
                'link_url' => $module->link_url,
                'has_file' => ! empty($module->file_path) && Storage::disk('local')->exists($module->file_path),
                'file_name' => $module->file_name,
                'file_size' => $module->file_size,
                'formatted_file_size' => $module->formatted_file_size,
                'file_mime' => $module->file_mime,
                'sort_order' => $module->sort_order,
                'created_at' => $module->created_at?->toIso8601String(),
                'updated_at' => $module->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('modules/Index', [
            'section' => [
                'id' => $section->id,
                'subject_code' => $section->subject_code,
                'subject_title' => $section->subject_title,
                'name' => $section->name,
                'term' => $section->academicTerm ? [
                    'name' => $section->academicTerm->name,
                    'school_year' => $section->academicTerm->school_year,
                ] : null,
            ],
            'modules' => $modules,
        ]);
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($request, $section);

        $validated = $request->validate([
            'module_number' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'file' => ['nullable', 'file', 'max:51200'], // 50MB max limit
        ], [
            'file.max' => 'The presentation file must not exceed 50 MB.',
            'link_url.url' => 'The presentation link must be a valid URL (e.g. https://docs.google.com/presentation/...).',
        ]);

        $filePath = null;
        $fileName = null;
        $fileSize = null;
        $fileMime = null;

        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $filePath = $uploadedFile->store("classcheck/modules/{$section->id}", 'local');
            $fileName = $uploadedFile->getClientOriginalName();
            $fileSize = $uploadedFile->getSize();
            $fileMime = $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType();
        }

        $nextSortOrder = ($section->courseModules()->max('sort_order') ?? 0) + 1;

        $section->courseModules()->create([
            'module_number' => trim($validated['module_number']),
            'title' => trim($validated['title']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'link_url' => ! empty($validated['link_url']) ? trim($validated['link_url']) : null,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'file_mime' => $fileMime,
            'sort_order' => $nextSortOrder,
        ]);

        return back()->with('success', "{$validated['module_number']}: {$validated['title']} added successfully.");
    }

    public function update(Request $request, Section $section, CourseModule $courseModule): RedirectResponse
    {
        $this->authorizeSection($request, $section);
        abort_unless((int) $courseModule->section_id === (int) $section->id, 404);

        $validated = $request->validate([
            'module_number' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'file' => ['nullable', 'file', 'max:51200'],
            'remove_file' => ['nullable', 'boolean'],
        ], [
            'file.max' => 'The presentation file must not exceed 50 MB.',
            'link_url.url' => 'The presentation link must be a valid URL.',
        ]);

        $updateData = [
            'module_number' => trim($validated['module_number']),
            'title' => trim($validated['title']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'link_url' => ! empty($validated['link_url']) ? trim($validated['link_url']) : null,
        ];

        if ($request->boolean('remove_file') && ! empty($courseModule->file_path)) {
            if (Storage::disk('local')->exists($courseModule->file_path)) {
                Storage::disk('local')->delete($courseModule->file_path);
            }
            $updateData['file_path'] = null;
            $updateData['file_name'] = null;
            $updateData['file_size'] = null;
            $updateData['file_mime'] = null;
        }

        if ($request->hasFile('file')) {
            if (! empty($courseModule->file_path) && Storage::disk('local')->exists($courseModule->file_path)) {
                Storage::disk('local')->delete($courseModule->file_path);
            }

            $uploadedFile = $request->file('file');
            $updateData['file_path'] = $uploadedFile->store("classcheck/modules/{$section->id}", 'local');
            $updateData['file_name'] = $uploadedFile->getClientOriginalName();
            $updateData['file_size'] = $uploadedFile->getSize();
            $updateData['file_mime'] = $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType();
        }

        $courseModule->update($updateData);

        return back()->with('success', "{$courseModule->module_number} updated successfully.");
    }

    public function destroy(Request $request, Section $section, CourseModule $courseModule): RedirectResponse
    {
        $this->authorizeSection($request, $section);
        abort_unless((int) $courseModule->section_id === (int) $section->id, 404);

        if (! empty($courseModule->file_path) && Storage::disk('local')->exists($courseModule->file_path)) {
            Storage::disk('local')->delete($courseModule->file_path);
        }

        $name = "{$courseModule->module_number}: {$courseModule->title}";
        $courseModule->delete();

        return back()->with('success', "{$name} deleted.");
    }

    public function download(Request $request, Section $section, CourseModule $courseModule): StreamedResponse|BinaryFileResponse
    {
        $this->authorizeSection($request, $section);
        abort_unless((int) $courseModule->section_id === (int) $section->id, 404);
        abort_unless($courseModule->file_path && Storage::disk('local')->exists($courseModule->file_path), 404);

        $name = $courseModule->file_name ?? basename($courseModule->file_path);
        $mime = $courseModule->file_mime ?? Storage::disk('local')->mimeType($courseModule->file_path) ?? 'application/octet-stream';

        if ($request->boolean('download')) {
            return Storage::disk('local')->download(
                $courseModule->file_path,
                $name,
                ['Content-Type' => $mime]
            );
        }

        return Storage::disk('local')->response(
            $courseModule->file_path,
            $name,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
            ]
        );
    }

    public function reorder(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($request, $section);

        $data = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:course_modules,id'],
        ]);

        foreach ($data['ordered_ids'] as $index => $id) {
            $section->courseModules()->where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Modules reordered successfully.');
    }

    private function authorizeSection(Request $request, Section $section): void
    {
        abort_unless((int) $section->user_id === (int) $request->user()?->id, 403);
    }
}
