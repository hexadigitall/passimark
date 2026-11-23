<?php

namespace App\Http\Controllers;

use App\Services\PassimarkImport\VceSourceAdapter;
use App\Services\PassimarkPackage\PackagingSpec;
use App\Support\PsmkZip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopController extends Controller
{
    /**
     * Desktop runtime specs, versioning, and feature capabilities.
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'engine' => 'Tauri 2.0 Rust Core',
            'desktop_version' => '2.0.0',
            'client_parity' => 'VCE Superiority Standard',
            'supported_file_associations' => [
                'psmk' => 'Passimark Certification Container (.psmk)',
                'psme' => 'Passimark Encrypted Exam Container (.psme)',
                'vce' => 'Visual CertExam Container (.vce)',
            ],
            'capabilities' => [
                'multi_window_exhibits' => true,
                'detached_scratchpad' => true,
                'detached_calculator' => true,
                'vce_single_key_shortcuts' => true,
                'offline_sqlite' => true,
                'hardware_acceleration' => true,
            ],
            'memory_target_mb' => '< 45 MB RAM',
            'cold_boot_target_ms' => '< 500 ms',
        ]);
    }

    /**
     * Inspect and validate local package file (.psmk or .vce) on desktop open.
     */
    public function inspect(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'nullable|string',
            'file' => 'nullable|file',
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->getRealPath();
        } elseif ($request->filled('path')) {
            $filePath = $request->input('path');
        }

        if (!$filePath || !file_exists($filePath)) {
            return response()->json([
                'is_valid' => false,
                'error' => 'Target file does not exist or is not readable.',
            ], 422);
        }

        $filename = basename($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $fileSize = filesize($filePath);

        $isValid = false;
        $format = 'unknown';
        $summary = [];

        if ($extension === 'psmk') {
            $format = 'passimark_package';
            try {
                $binary = file_get_contents($filePath);
                $files = PsmkZip::read($binary);
                $hasManifest = isset($files['manifest.json']);
                $hasContent = isset($files['content.json']);
                $isValid = $hasManifest && $hasContent;

                if ($hasManifest) {
                    $manifestData = json_decode($files['manifest.json'], true);
                    $summary['manifest'] = $manifestData;
                }
            } catch (\Throwable $e) {
                $isValid = false;
                $summary['error'] = $e->getMessage();
            }
        } elseif ($extension === 'vce') {
            $format = 'visual_certexam';
            try {
                $adapter = app(VceSourceAdapter::class);
                $extracted = $adapter->extract($filePath);
                $isValid = !empty($extracted['questions']);
                $summary['questions_count'] = count($extracted['questions'] ?? []);
                $summary['assets_count'] = count($extracted['assets'] ?? []);
            } catch (\Throwable $e) {
                $isValid = false;
                $summary['error'] = $e->getMessage();
            }
        }

        return response()->json([
            'is_valid' => $isValid,
            'filename' => $filename,
            'extension' => $extension,
            'format' => $format,
            'size_bytes' => $fileSize,
            'summary' => $summary,
        ]);
    }
}
