<?php
namespace App\Services;

use App\Models\{PassimarkCertificationTrack, PassimarkProgress, PassimarkSession, User};

/**
 * Curriculum enrollment + ladder rule (v4).
 *
 * A learner is "enrolled" by opening the first assessable step of a track. Passing a
 * lesson/phase/domain/mock opens the next assessable step in the same track (theta/pass
 * gate). Finals and legacy sessions (phase_type null) stay instructor-approval gated.
 * Steps without questions (reading/remediation) are skipped by the ladder.
 */
class Curriculum
{
    /**
     * Open the first assessable step of every active track for a learner.
     *
     * @return int number of tracks the learner was enrolled into
     */
    public static function enrollFirstSteps(User $user): int
    {
        $enrolled = 0;
        foreach (PassimarkCertificationTrack::where('is_active', true)->orderBy('id')->get() as $track) {
            if (self::enrollInTrack($user, $track)) {
                $enrolled++;
            }
        }
        return $enrolled;
    }

    public static function enrollInTrack(User $user, PassimarkCertificationTrack $track): bool
    {
        $first = self::firstAssessableSession($track->id);
        if (!$first) {
            return false;
        }
        PassimarkProgress::firstOrCreate(
            ['user_id' => $user->id, 'session_id' => $first->id],
            ['status' => 'open', 'ability_theta' => 0, 'attempts' => 0]
        );
        return true;
    }

    public static function firstAssessableSession(int $trackId): ?PassimarkSession
    {
        return PassimarkSession::where('certification_track_id', $trackId)
            ->where('question_count', '>', 0)
            ->where('is_optional', false)
            ->orderBy('order')
            ->first();
    }

    /**
     * Open the next required assessable session after a pass, plus any optional remediation
     * sessions positioned before it. Returns the unlocked required session, or null when the
     * ladder is closed/finished.
     *
     * Optional sessions (is_optional — mock-error remediation practice) never gate the ladder:
     * they are unlocked alongside their checkpoint and skipped when picking the next required step.
     * Finals never auto-unlock (certificate issuance, Sprint 8). Legacy sessions (phase_type null)
     * still unlock on instructor approval via the order ladder.
     */
    public static function unlockNext(PassimarkSession $session, int $userId): ?PassimarkSession
    {
        self::unlockOptionalBetween($session, $userId);

        if (in_array($session->phase_type, ['cert', 'final'], true)) {
            return null;
        }
        $next = PassimarkSession::where('certification_track_id', $session->certification_track_id)
            ->where('question_count', '>', 0)
            ->where('is_optional', false)
            ->where('order', '>', $session->order)
            ->orderBy('order')
            ->first();
        if (!$next) {
            return null;
        }
        PassimarkProgress::firstOrCreate(
            ['user_id' => $userId, 'session_id' => $next->id],
            ['status' => 'open', 'ability_theta' => 0, 'attempts' => 0]
        );
        return $next;
    }

    /**
     * Open the optional remediation sessions that sit between the just-passed session and the
     * next required step (or all remaining optional sessions when nothing follows).
     */
    private static function unlockOptionalBetween(PassimarkSession $session, int $userId): void
    {
        $next = PassimarkSession::where('certification_track_id', $session->certification_track_id)
            ->where('question_count', '>', 0)
            ->where('is_optional', false)
            ->where('order', '>', $session->order)
            ->orderBy('order')
            ->first();

        $optional = PassimarkSession::where('certification_track_id', $session->certification_track_id)
            ->where('question_count', '>', 0)
            ->where('is_optional', true)
            ->where('order', '>', $session->order)
            ->when($next, fn ($q) => $q->where('order', '<', $next->order))
            ->get();

        foreach ($optional as $candidate) {
            PassimarkProgress::firstOrCreate(
                ['user_id' => $userId, 'session_id' => $candidate->id],
                ['status' => 'open', 'ability_theta' => 0, 'attempts' => 0]
            );
        }
    }
}
