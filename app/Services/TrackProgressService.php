<?php

namespace App\Services;

use App\Models\PassimarkAttempt;
use App\Models\PassimarkAttemptAnswer;
use App\Models\PassimarkProgress;

/**
 * Learner progress roll-ups used by the catalog drill-down (Sprint 7.8).
 *
 * The dashboard used to compute θ history and domain accuracy per track inline, which
 * is O(tracks) queries. These helpers expose both the heavy per-track reads used on the
 * track screen and the bulk grouped reads used to build category/bundle tiles without
 * N+1 queries.
 */
class TrackProgressService
{
    /** Statuses that count a session as done for progress bars. */
    public const DONE_STATUSES = ['completed', 'pending_approval', 'approved'];

    /**
     * Final θ of each finished attempt, oldest first (bounded to the last 12).
     *
     * @return list<float>
     */
    public function thetaHistory(int $trackId, int $userId): array
    {
        return PassimarkAttempt::query()
            ->join('passimark_sessions', 'passimark_sessions.id', '=', 'passimark_attempts.session_id')
            ->where('passimark_sessions.certification_track_id', $trackId)
            ->where('passimark_attempts.user_id', $userId)
            ->whereNotNull('passimark_attempts.finished_at')
            ->whereNotNull('passimark_attempts.theta')
            ->orderBy('passimark_attempts.finished_at')
            ->limit(12)
            ->pluck('passimark_attempts.theta')
            ->map(fn ($theta) => (float) $theta)
            ->values()
            ->all();
    }

    /**
     * Weak-zone heatmap: per-domain accuracy across a learner's answered items for a track.
     *
     * @return list<array{name: string, total: int, correct: int, accuracy: float}>
     */
    public function domainAccuracy(int $trackId, ?int $userId = null): array
    {
        $rows = PassimarkAttemptAnswer::query()
            ->join('passimark_questions', 'passimark_questions.id', '=', 'passimark_attempt_answers.question_id')
            ->join('passimark_sessions', 'passimark_sessions.id', '=', 'passimark_questions.session_id')
            ->when($userId, fn ($q) => $q
                ->join('passimark_attempts', 'passimark_attempts.id', '=', 'passimark_attempt_answers.attempt_id')
                ->where('passimark_attempts.user_id', $userId))
            ->where('passimark_sessions.certification_track_id', $trackId)
            ->whereNotNull('passimark_questions.domain')
            ->selectRaw('passimark_questions.domain as name, COUNT(*) as total, SUM(CASE WHEN passimark_attempt_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct')
            ->groupBy('passimark_questions.domain')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($row) => [
            'name' => $row->name,
            'total' => (int) $row->total,
            'correct' => (int) $row->correct,
            'accuracy' => $row->total > 0 ? round($row->correct / $row->total, 4) : 0.0,
        ])->values()->all();
    }

    /**
     * Done-session counts per track for a learner, keyed by track id. One grouped query.
     *
     * @return array<int,int>
     */
    public function doneCountsByTrack(int $userId): array
    {
        return \Illuminate\Support\Facades\DB::table('passimark_progress')
            ->join('passimark_sessions', 'passimark_sessions.id', '=', 'passimark_progress.session_id')
            ->where('passimark_progress.user_id', $userId)
            ->whereIn('passimark_progress.status', self::DONE_STATUSES)
            ->selectRaw('passimark_sessions.certification_track_id as track_id, COUNT(*) as done')
            ->groupBy('passimark_sessions.certification_track_id')
            ->pluck('done', 'track_id')
            ->map(fn ($done) => (int) $done)
            ->all();
    }

    /**
     * Best θ per track for a learner, keyed by track id. One grouped query.
     *
     * @return array<int,float>
     */
    public function thetaByTrack(int $userId): array
    {
        return \Illuminate\Support\Facades\DB::table('passimark_attempts')
            ->join('passimark_sessions', 'passimark_sessions.id', '=', 'passimark_attempts.session_id')
            ->where('passimark_attempts.user_id', $userId)
            ->whereNotNull('passimark_attempts.theta')
            ->selectRaw('passimark_sessions.certification_track_id as track_id, MAX(passimark_attempts.theta) as theta')
            ->groupBy('passimark_sessions.certification_track_id')
            ->pluck('theta', 'track_id')
            ->map(fn ($theta) => (float) $theta)
            ->all();
    }

    /**
     * The learner's most recently touched open/in-progress assessable session, used for
     * the dashboard "continue where you left off" hero.
     *
     * @return array{session_id:int,title:string,number:?int,phase_type:?string,status:string,score:?float,track_slug:string,track_title:string,cert_key:string}|null
     */
    public function continueSession(int $userId): ?array
    {
        $progress = PassimarkProgress::query()
            ->with(['session.certificationTrack'])
            ->where('user_id', $userId)
            ->whereIn('status', ['open', 'in_progress'])
            ->whereHas('session', fn ($q) => $q->where('question_count', '>', 0))
            ->orderByDesc('updated_at')
            ->first();

        $session = $progress?->session;
        $track = $session?->certificationTrack;
        if (!$session || !$track) {
            return null;
        }

        return [
            'session_id' => $session->id,
            'title' => $session->title,
            'number' => $session->number,
            'phase_type' => $session->phase_type,
            'status' => $progress->status,
            'score' => $progress->score,
            'track_slug' => $track->slug,
            'track_title' => $track->title,
            'cert_key' => $track->certKey(),
        ];
    }
}
