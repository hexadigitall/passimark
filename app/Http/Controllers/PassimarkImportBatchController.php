<?php

namespace App\Http\Controllers;

use App\Models\PassimarkImportBatch;
use App\Models\PassimarkSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PassimarkImportBatchController extends Controller
{
    /**
     * Display a listing of staged import batches.
     */
    public function index(Request $request)
    {
        $batches = PassimarkImportBatch::with('user:id,name,email')
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($batches);
        }

        return Inertia::render('Passimark/Import/Index', [
            'batches' => $batches,
            'sessions' => PassimarkSession::orderBy('order')->get(['id', 'number', 'title', 'cert_slug']),
        ]);
    }

    /**
     * Show the batch staging review screen.
     */
    public function show(Request $request, string $id)
    {
        $batch = PassimarkImportBatch::with(['items', 'user:id,name,email'])->findOrFail($id);

        if ($request->wantsJson()) {
            return response()->json(['batch' => $batch]);
        }

        return Inertia::render('Passimark/Import/Show', [
            'batch' => $batch,
            'sessions' => PassimarkSession::orderBy('order')->get(['id', 'number', 'title', 'cert_slug']),
        ]);
    }

    /**
     * Delete an import batch and its staged items.
     */
    public function destroy(string $id)
    {
        $batch = PassimarkImportBatch::findOrFail($id);
        $batch->delete();

        if (request()->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('admin.imports.index')->with('success', 'Import batch deleted.');
    }
}
