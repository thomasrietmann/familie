<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentImportRequest;
use App\Models\DocumentImport;
use App\Models\Family;
use App\Services\DocumentEventExtractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
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
        $analysisFailed = false;

        try {
            $documentImport = DB::transaction(fn () => $family->documentImports()->create([
                'uploaded_by_user_id' => $request->user()->id,
                'title' => $request->validated('title'),
                'file_path' => null,
                'original_filename' => $file->getClientOriginalName(),
                'file_type' => $file->extension(),
                'status' => 'uploaded',
                'notes' => $request->validated('notes'),
            ] + $request->targetData()));
            try {
                $results = $service->extract($documentImport, $file);

                DB::transaction(function () use ($results, $documentImport, $family): void {
                    foreach ($results as $result) {
                        $documentImport->suggestions()->create($result + ['family_id' => $family->id, 'status' => 'pending']);
                    }

                    $documentImport->update([
                        'status' => 'analyzed',
                        'raw_ai_result' => null,
                    ]);
                });
            } catch (Throwable $exception) {
                $analysisFailed = true;
                $documentImport->update([
                    'status' => 'failed',
                    'raw_ai_result' => null,
                ]);
                Log::warning('Document analysis failed', ['document_import_id' => $documentImport->id, 'exception_class' => $exception::class]);
            }
        } finally {
            // PHP also removes request uploads at shutdown; explicitly remove ours on every controller exit.
            if (is_file($file->getPathname()) && ! @unlink($file->getPathname())) {
                throw new RuntimeException('Temporärer Upload konnte nicht gelöscht werden.');
            }
        }

        if ($analysisFailed) {
            return redirect()
                ->route('families.document-imports.show', [$family, $documentImport])
                ->withErrors(['document' => 'Die Dokumentanalyse ist fehlgeschlagen. Die hochgeladene Originaldatei wurde gelöscht. Bitte später erneut hochladen.']);
        }

        return redirect()->route('document-imports.review', $documentImport)->with('status', 'Dokument wurde mit OpenAI analysiert. Die hochgeladene Originaldatei wurde gelöscht.');
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
        $documentImport->load([
            'family.children',
            'family.activeParents',
            'suggestions' => fn ($query) => $query->where('status', 'pending')->orderBy('starts_at'),
        ]);

        return view('document-imports.review', compact('documentImport'));
    }
}
