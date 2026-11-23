<?php

namespace App\Http\Controllers;

use App\Models\PassimarkAttempt;
use App\Models\PassimarkAttemptAnswer;
use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Services\CertificateIssuer;
use App\Services\Curriculum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileSyncController extends Controller
{
    /**
     * Download question banks, tracks, sessions, and progress for offline mobile caching.
     */
    public function download(Request $request): JsonResponse
    {
        $user = $request->user();

        // Retrieve active tracks
        $tracks = PassimarkCertificationTrack::where('is_active', true)->get();
        $trackIds = $tracks->pluck('id');

        // Retrieve all sessions for active tracks
        $sessions = PassimarkSession::whereIn('certification_track_id', $trackIds)
            ->orderBy('order')
            ->get();
        $sessionIds = $sessions->pluck('id');

        // Retrieve exams
        $exams = PassimarkExam::whereIn('session_id', $sessionIds)->get();

        // Retrieve questions
        $questions = PassimarkQuestion::whereIn('session_id', $sessionIds)
            ->select([
                'id',
                'session_id',
                'exam_id',
                'type',
                'content',
                'options',
                'correct_key',
                'difficulty',
                'discrimination',
                'guessing',
                'domain',
                'bloom_level',
                'explanation',
                'reference',
                'interactive_payload',
                'exhibits',
            ])
            ->get();

        // User's current progress
        $progress = $user ? PassimarkProgress::where('user_id', $user->id)->get() : [];

        return response()->json([
            'version' => '1.0',
            'timestamp' => now()->toRfc3339String(),
            'tracks' => $tracks,
            'sessions' => $sessions,
            'exams' => $exams,
            'questions' => $questions,
            'progress' => $progress,
        ]);
    }

    /**
     * Upload offline-completed attempts and answer logs with anti-cheat timestamp verification.
     */
    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'attempts' => 'required|array|min:1',
            'attempts.*.local_id' => 'required|string',
            'attempts.*.session_id' => 'required|exists:passimark_sessions,id',
            'attempts.*.exam_id' => 'nullable|exists:passimark_exams,id',
            'attempts.*.mode' => 'required|in:cat,timed,practice',
            'attempts.*.theta' => 'nullable|numeric',
            'attempts.*.theta_se' => 'nullable|numeric',
            'attempts.*.score' => 'required|numeric|min:0|max:100',
            'attempts.*.passed' => 'required|boolean',
            'attempts.*.started_at' => 'required|date',
            'attempts.*.completed_at' => 'required|date',
            'attempts.*.time_spent' => 'required|integer|min:0',
            'attempts.*.answers' => 'required|array',
            'attempts.*.answers.*.question_id' => 'required|exists:passimark_questions,id',
            'attempts.*.answers.*.is_correct' => 'required|boolean',
            'attempts.*.answers.*.time_spent' => 'nullable|integer|min:0',
            'attempts.*.answers.*.interaction_response' => 'nullable',
        ]);

        $syncedAttempts = [];
        $mintedCertificates = [];

        DB::transaction(function () use ($user, $data, &$syncedAttempts, &$mintedCertificates) {
            foreach ($data['attempts'] as $attData) {
                $startedAt = Carbon::parse($attData['started_at']);
                $completedAt = Carbon::parse($attData['completed_at']);

                // Anti-cheat verification: completed_at cannot precede started_at
                if ($completedAt->lt($startedAt)) {
                    $completedAt = (clone $startedAt)->addSeconds($attData['time_spent']);
                }

                // Check for duplicate sync (idempotency by local_id)
                $existing = PassimarkAttempt::where('user_id', $user->id)
                    ->where('session_state->local_id', $attData['local_id'])
                    ->first();

                if ($existing) {
                    $syncedAttempts[] = [
                        'local_id' => $attData['local_id'],
                        'server_id' => $existing->id,
                        'status' => 'already_synced',
                    ];
                    continue;
                }

                // Resolve exam_id if missing
                $examId = $attData['exam_id'] ?? null;
                if (!$examId) {
                    $examId = PassimarkExam::where('session_id', $attData['session_id'])->value('id');
                }

                $attempt = PassimarkAttempt::create([
                    'user_id' => $user->id,
                    'session_id' => $attData['session_id'],
                    'exam_id' => $examId,
                    'mode' => $attData['mode'],
                    'theta' => $attData['theta'] ?? 0.0,
                    'score' => $attData['score'],
                    'is_passed' => (bool) $attData['passed'],
                    'started_at' => $startedAt,
                    'finished_at' => $completedAt,
                    'session_state' => [
                        'local_id' => $attData['local_id'],
                        'offline_synced' => true,
                        'theta_se' => $attData['theta_se'] ?? null,
                        'time_spent' => $attData['time_spent'],
                    ],
                ]);

                // Record answers
                foreach ($attData['answers'] as $ansData) {
                    PassimarkAttemptAnswer::create([
                        'attempt_id' => $attempt->id,
                        'question_id' => $ansData['question_id'],
                        'is_correct' => $ansData['is_correct'],
                        'time_spent' => $ansData['time_spent'] ?? 0,
                        'interaction_response' => $ansData['interaction_response'] ?? null,
                    ]);
                }

                // Update session progress
                $session = PassimarkSession::find($attData['session_id']);
                if ($session) {
                    $progress = PassimarkProgress::firstOrCreate(
                        ['user_id' => $user->id, 'session_id' => $session->id],
                        ['status' => 'in_progress', 'score' => 0]
                    );

                    if ($attData['passed'] && $progress->status !== 'completed') {
                        $progress->update([
                            'status' => 'completed',
                            'score' => max($progress->score ?? 0, (int) round($attData['score'])),
                            'completed_at' => $completedAt,
                        ]);

                        // Check if final milestone and mint certificate
                        if ($session->phase_type === 'final' || ($examId && PassimarkExam::find($examId)?->is_final)) {
                            if (class_exists(CertificateIssuer::class)) {
                                $cert = CertificateIssuer::issueIfEligible($progress);
                                if ($cert && $cert->credential_id) {
                                    $mintedCertificates[] = [
                                        'credential_id' => $cert->credential_id,
                                        'certified_at' => $cert->certified_at,
                                    ];
                                }
                            }
                        }
                    }
                }

                $syncedAttempts[] = [
                    'local_id' => $attData['local_id'],
                    'server_id' => $attempt->id,
                    'status' => 'synced',
                ];
            }
        });

        return response()->json([
            'synced_count' => count($syncedAttempts),
            'attempts' => $syncedAttempts,
            'certificates_minted' => $mintedCertificates,
        ]);
    }
}
