<?php

namespace App\Http\Controllers;

use App\Models\PassimarkPackageImport;
use App\Services\PassimarkPackage\PackageImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Sprint 9 Phase C — instructor/admin upload, dry-run preview, and import of .psmk packages.
 * The controller only moves bytes and shapes responses; all archive/contract rules live in
 * PackageValidator/PackageImporter so the CLI and HTTP paths stay identical.
 */
class PassimarkPackageController extends Controller
{
    public function page()
    {
        return Inertia::render('Passimark/PackageImport', [
            'imports' => PassimarkPackageImport::latest('id')->limit(25)->get([
                'id', 'original_filename', 'package_id', 'content_type', 'status', 'created_at',
            ]),
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate(['package' => 'required|file|max:10240']);

        return response()->json(
            PackageImporter::preview($request->file('package')->getRealPath(), $this->placementOptions($request))
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'package' => 'required|file|max:10240',
            'on_duplicate' => 'nullable|in:create,skip,fail',
            'mode' => 'nullable|in:create,update',
        ]);

        try {
            $summary = PackageImporter::import($request->file('package')->getRealPath(), [
                ...$this->placementOptions($request),
                'uploaded_by' => $request->user()->id,
                'original_filename' => $request->file('package')->getClientOriginalName(),
                'on_duplicate' => $request->input('on_duplicate') ?: 'skip',
                'mode' => $request->input('mode') ?: 'create',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($summary);
    }

    private function placementOptions(Request $request): array
    {
        return [
            'cert_slug' => $request->input('cert_slug') ?: null,
            'cert_key' => $request->input('cert_key') ?: null,
            'variant_label' => $request->input('variant_label') ?: null,
        ];
    }
}
