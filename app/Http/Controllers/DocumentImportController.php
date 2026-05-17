<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentImportRequest;
use App\Models\DocumentImport;
use App\Models\Family;
use App\Services\DocumentEventExtractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class DocumentImportController extends Controller
{
    public function index(Family $family): View
    {
        $this->authorize('view', $family);
        $documentImports = $family->documentImports()->with(['uploadedBy'])->withCount('suggestions')->latest()->paginate(20);

        return view('document-imports.index', compact('family', 'documentImports'));
    }

    public function create(Family $family): View
    {
        $this->authorize('update', $family);
        $family->load(['children', 'activeParents']);

        return view('document-imports.create', compact('family'));
    }

    public function store(StoreDocumentImportRequest $request, Family $family, DocumentEventExtractionService $service): RedirectResponse
    {
        $file = $request->file('document');
        $path = $file->store('document-imports', 'public');

        $documentImport = $family->documentImports()->create([
            'uploaded_by_user_id' => $request->user()->id,
            'title' => $request->validated('title'),
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_type' => strtolower($file->getClientOriginalExtension()),
            'status' => 'uploaded',
            'notes' => $request->validated('notes'),
        ] + $request->targetData());

        try {
            $results = $service->extract($documentImport);

            foreach ($results as $result) {
                $documentImport->suggestions()->create($result + ['family_id' => $family->id, 'status' => 'pending']);
            }

            $documentImport->update([
                'status' => 'analyzed',
                'raw_ai_result' => $results,
            ]);

            return redirect()->route('document-imports.review', $documentImport)->with('status', 'Dokument wurde mit OpenAI analysiert.');
        } catch (Throwable $exception) {
            $documentImport->update([
                'status' => 'failed',
                'raw_ai_result' => ['error' => $exception->getMessage()],
            ]);

            return redirect()
                ->route('families.document-imports.show', [$family, $documentImport])
                ->withErrors(['document' => 'Die OpenAI Analyse ist fehlgeschlagen: '.$exception->getMessage()]);
        }
    }

    public function show(Family $family, DocumentImport $documentImport): View
    {
        $this->authorize('view', $documentImport);
        $documentImport->load('suggestions', 'uploadedBy');

        return view('document-imports.show', compact('family', 'documentImport'));
    }

    public function review(DocumentImport $documentImport): View
    {
        $this->authorize('update', $documentImport);
        $documentImport->load(['family.children', 'family.activeParents', 'suggestions' => fn ($query) => $query->orderBy('starts_at')]);

        return view('document-imports.review', compact('documentImport'));
    }
}
