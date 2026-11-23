<?php

namespace App\Http\Controllers;

use App\Models\PassimarkImportBatch;
use App\Models\PassimarkImportItem;
use App\Models\PassimarkSession;
use App\Services\PassimarkImport\ImportStagingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PassimarkImportController extends Controller
{
    public function __construct(protected ImportStagingService $stagingService)
    {
    }

    /**
     * Upload and parse a source file (VCE, CSV, GIFT, XML, QTI) into staging.
     */
    public function upload(Request $request, ?string $import = null)
    {
        $request->validate([
            'file' => 'required|file|max:51200', // max 50MB
            'source_type' => 'nullable|string',
            'title' => 'nullable|string|max:255',
            'cert_slug' => 'nullable|string|max:255',
            'pass_score' => 'nullable|integer|min:1|max:100',
            'time_minutes' => 'nullable|integer|min:1|max:600',
        ]);

        $batch = $this->stagingService->createBatch(
            $request->file('file'),
            $request->input('source_type', 'auto'),
            $request->user(),
            $request->only(['title', 'cert_slug', 'pass_score', 'time_minutes', 'default_domain'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Successfully parsed and staged {$batch->total_items} item(s).",
                'batch' => $batch->load('items'),
            ], Response::HTTP_CREATED);
        }

        return redirect()->route('admin.imports.show', $batch->id)
            ->with('success', "Parsed {$batch->total_items} items into staging.");
    }

    /**
     * Re-extract from source file if required.
     */
    public function extract(string $import)
    {
        $batch = PassimarkImportBatch::findOrFail($import);
        if (!$batch->file_path || !is_file($batch->file_path)) {
            return response()->json(['error' => 'Original source file no longer available.'], 404);
        }

        // Re-run staging pipeline
        $newBatch = $this->stagingService->createBatch(
            $batch->file_path,
            $batch->source_type,
            $batch->user,
            $batch->metadata ?? []
        );

        return response()->json(['batch' => $newBatch]);
    }

    /**
     * Re-run normalization and validation on staged items.
     */
    public function normalize(string $import)
    {
        $batch = PassimarkImportBatch::with('items')->findOrFail($import);

        $valid = 0;
        $error = 0;

        foreach ($batch->items as $item) {
            $errs = [];
            if (trim($item->prompt) === '') {
                $errs[] = 'Prompt is empty.';
            }
            if ($item->item_type === 'single_choice' && count(array_filter($item->choices ?? [], fn ($c) => $c['is_correct'] ?? false)) === 0) {
                $errs[] = 'No correct choice marked.';
            }

            $status = empty($errs) ? 'valid' : 'error';
            $item->update(['status' => $status, 'validation_errors' => !empty($errs) ? $errs : null]);

            if ($status === 'valid') {
                $valid++;
            } else {
                $error++;
            }
        }

        $batch->update(['valid_items' => $valid, 'error_items' => $error]);

        return response()->json(['batch' => $batch->fresh('items')]);
    }

    /**
     * Review, approve, reject, or edit individual staged items.
     */
    public function review(Request $request, string $import)
    {
        $batch = PassimarkImportBatch::findOrFail($import);

        $action = $request->input('action', 'approve');
        $itemId = $request->input('item_id');

        if ($action === 'approve_all') {
            $batch->items()->where('status', 'valid')->update(['status' => 'approved']);
            return response()->json(['message' => 'All valid items marked as approved.']);
        }

        $item = PassimarkImportItem::where('batch_id', $batch->id)->findOrFail($itemId);

        if ($action === 'approve') {
            $overrides = $request->input('overrides', []);
            if (!empty($overrides)) {
                $item->fill($overrides);
            }
            $item->status = 'approved';
            $item->validation_errors = null;
            $item->save();
        } elseif ($action === 'reject') {
            $item->status = 'rejected';
            $item->save();
        } elseif ($action === 'update') {
            $item->update($request->only([
                'prompt', 'item_type', 'choices', 'correct_answers',
                'explanation', 'reference', 'domain', 'bloom',
                'difficulty', 'discrimination', 'guessing', 'interactive_payload'
            ]));
        }

        return response()->json(['item' => $item]);
    }

    /**
     * Export approved items: either to downloadable .psmk archive or inject into a session.
     */
    public function export(Request $request, string $import)
    {
        $batch = PassimarkImportBatch::with('items')->findOrFail($import);

        // Check if direct insertion into session requested
        $sessionId = $request->input('session_id');
        if ($sessionId) {
            $session = PassimarkSession::findOrFail($sessionId);
            $result = $this->stagingService->importBatchToSession($batch, $session);
            return response()->json([
                'message' => "Successfully imported {$result['imported']} question(s) into {$session->title}.",
                'imported' => $result['imported'],
            ]);
        }

        // Export to .psmk binary
        $package = $this->stagingService->exportToPsmk($batch, $request->all());

        if ($request->input('download') || !$request->wantsJson()) {
            return response($package['binary'], 200, [
                'Content-Type' => 'application/vnd.passimark.package',
                'Content-Disposition' => "attachment; filename=\"{$package['filename']}\"",
                'Content-Length' => $package['size'],
            ]);
        }

        return response()->json([
            'message' => "Successfully generated portable .psmk package with {$package['items_count']} questions and {$package['assets_count']} asset(s).",
            'package_id' => $package['package_id'],
            'filename' => $package['filename'],
            'size' => $package['size'],
            'items_count' => $package['items_count'],
            'assets_count' => $package['assets_count'],
        ]);
    }
}
