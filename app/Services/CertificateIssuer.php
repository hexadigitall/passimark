<?php

namespace App\Services;

use App\Models\PassimarkProgress;

/**
 * Certificate issuance (Sprint 8).
 *
 * A credential is minted once, when a learner passes a track's final assessment:
 *  - advancement=auto tracks certify as soon as the final is passed (status completed);
 *  - approval-gated tracks certify only after an instructor approves the final pass.
 *
 * The credential id is deterministic per learner+track (`PMK-{CERT}-{YEAR}-{HEX8}`), so a
 * re-issue attempt yields the same code; `credential_hash` is a tamper-evident placeholder for
 * future Polygon/IPFS anchoring.
 */
class CertificateIssuer
{
    public const PREFIX = 'PMK';

    /**
     * Mint a credential when the progress row represents a passed final. Idempotent and safe to
     * call on every finish/approve: returns null when the row is not (yet) eligible.
     */
    public static function issueIfEligible(PassimarkProgress $progress): ?PassimarkProgress
    {
        $progress->loadMissing('session.certificationTrack');
        $session = $progress->session;
        if (!$session || $session->phase_type !== 'final') {
            return null;
        }
        if ($progress->certified_at) {
            return $progress;
        }

        $track = $session->certificationTrack;
        $approvalGated = ($track->advancement ?? 'approval') !== 'auto';
        $eligible = $approvalGated
            ? $progress->status === PassimarkProgress::APPROVED
            : in_array($progress->status, [PassimarkProgress::COMPLETED, PassimarkProgress::APPROVED], true);
        if (!$eligible) {
            return null;
        }

        $theta = (float) ($progress->ability_theta ?? 0);
        $required = (float) ($session->theta_required ?? 0);
        if ($theta < $required) {
            return null;
        }

        $credentialId = self::credentialId($progress);
        $passProbability = self::passProbability($theta, $required);
        $issuedAt = now();
        $hash = hash('sha256', implode('|', [
            $credentialId,
            $progress->user_id,
            $progress->session_id,
            number_format($theta, 4, '.', ''),
            number_format($passProbability, 4, '.', ''),
            $issuedAt->toIso8601String(),
        ]));

        $progress->forceFill([
            'credential_id' => $credentialId,
            'certified_at' => $issuedAt,
            'pass_probability' => round($passProbability, 4),
            'credential_hash' => 'sha256:'.$hash,
        ])->save();

        return $progress;
    }

    /**
     * 1PL-style pass probability from the theta/pass-theta gap (logistic, 1.7 slope).
     */
    public static function passProbability(float $theta, float $required): float
    {
        return 1 / (1 + exp(-1.7 * ($theta - $required)));
    }

    /**
     * Deterministic credential id: PMK-{CERT}-{YEAR}-{HEX8}, where the hex digest is stable for
     * a given learner+track (so re-issue can never produce a second code for the same award).
     */
    public static function credentialId(PassimarkProgress $progress): string
    {
        $progress->loadMissing('session.certificationTrack');
        $track = $progress->session?->certificationTrack;
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($track?->certKey() ?? $track?->slug ?? 'CERT')));
        $code = substr($code ?: 'CERT', 0, 12);
        $hex = strtoupper(substr(hash('sha256', 'passimark-credential|'.$progress->user_id.'|'.($track?->id ?? 0)), 0, 8));

        return sprintf('%s-%s-%s-%s', self::PREFIX, $code, now()->year, $hex);
    }

    /**
     * Learner-facing certificate payload (null when not certified).
     */
    public static function summary(?PassimarkProgress $progress): ?array
    {
        if (!$progress || !$progress->certified_at) {
            return null;
        }
        $progress->loadMissing('session.certificationTrack');
        $track = $progress->session?->certificationTrack;

        return [
            'credential_id' => $progress->credential_id,
            'certified_at' => $progress->certified_at?->toIso8601String(),
            'pass_probability' => $progress->pass_probability,
            'theta' => (float) ($progress->ability_theta ?? 0),
            'hash' => $progress->credential_hash,
            'certification' => $track?->title,
            'variant_label' => $track?->variant_label,
            'cert_key' => $track?->certKey(),
            'session' => $progress->session?->title,
            'url' => route('passimark.certificate', ['progress' => $progress->id]),
            'verify_url' => route('passimark.verify', ['credentialId' => $progress->credential_id]),
        ];
    }

    /**
     * Public verification payload for a credential id (null when unknown/not certified).
     */
    public static function verify(string $credentialId): ?array
    {
        $progress = PassimarkProgress::with(['session.certificationTrack', 'user'])
            ->where('credential_id', $credentialId)
            ->first();
        if (!$progress || !$progress->certified_at) {
            return null;
        }
        $track = $progress->session?->certificationTrack;

        return [
            'credential_id' => $progress->credential_id,
            'holder' => $progress->user?->name,
            'certification' => $track?->title,
            'variant_label' => $track?->variant_label,
            'cert_key' => $track?->certKey(),
            'issued_at' => $progress->certified_at?->toIso8601String(),
            'theta' => (float) ($progress->ability_theta ?? 0),
            'pass_probability' => $progress->pass_probability,
            'hash' => $progress->credential_hash,
        ];
    }
}
