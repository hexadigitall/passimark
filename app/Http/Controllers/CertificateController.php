<?php

namespace App\Http\Controllers;

use App\Models\PassimarkProgress;
use App\Services\CertificateIssuer;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CertificateController extends Controller
{
    /**
     * Learner's certificate for a certified session. Owner-only.
     */
    public function show(PassimarkProgress $progress)
    {
        abort_unless($progress->user_id === Auth::id() && $progress->certified_at, 404);

        return Inertia::render('Passimark/Certificate', [
            'certificate' => CertificateIssuer::summary($progress),
            'user' => Auth::user(),
        ]);
    }

    /**
     * Public credential verification endpoint (QR target). Renders a valid/invalid result page.
     */
    public function verify(string $credentialId)
    {
        $certificate = CertificateIssuer::verify($credentialId);

        return Inertia::render('Passimark/Verify', [
            'credentialId' => $credentialId,
            'valid' => $certificate !== null,
            'certificate' => $certificate,
        ]);
    }
}
