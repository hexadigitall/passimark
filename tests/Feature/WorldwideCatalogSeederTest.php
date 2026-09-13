<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkSession;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Services\CatEngine;
use Database\Seeders\WorldwidePassimarkCatalogSeeder;
use Database\Seeders\Uniform205CatalogSeeder;
use Tests\TestCase;

class WorldwideCatalogSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    private function student(): User
    {
        return User::where('email', 'student@passimark.com')->firstOrFail();
    }

    private function seedSecurityRegion(): void
    {
        (new WorldwidePassimarkCatalogSeeder)->seedCatalog($this->securityRegion());
    }

    private function securityRegion(): array
    {
        return [
            'USA-IT-SECURITY' => [
                'SEC+' => [
                    'name' => 'CompTIA Security+ SY0-701',
                    'desc' => 'Global entry cybersecurity',
                    'pass_score' => 75, 'final_q' => 90, 'final_time' => 90, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Threats & Vulnerabilities', 'phase_q' => 60, 'phase_time' => 70, 'lessons' => ['Threat Actors', 'Social Engineering', 'Malware Types', 'Vulnerability Scanning']],
                        ['name' => 'Architecture & Design', 'phase_q' => 60, 'phase_time' => 75, 'lessons' => ['Security Concepts', 'Secure Protocols', 'Cloud Security', 'Resilience']],
                        ['name' => 'Implementation & Operations', 'phase_q' => 65, 'phase_time' => 80, 'lessons' => ['Identity & Access', 'Cryptography', 'Wireless & Endpoint', 'Incident Response']],
                    ],
                ],
                'CISSP' => [
                    'name' => 'ISC2 CISSP - Gold Standard',
                    'desc' => 'CISSP 8 Domains - 150Q adaptive',
                    'pass_score' => 70, 'final_q' => 150, 'final_time' => 180, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Security & Risk Management', 'phase_q' => 75, 'phase_time' => 90, 'lessons' => ['Governance Frameworks', 'Risk Assessment', 'Legal Compliance']],
                        ['name' => 'Asset Security & Architecture', 'phase_q' => 70, 'phase_time' => 85, 'lessons' => ['Data Classification', 'Crypto Fundamentals', 'Security Models']],
                        ['name' => 'Network & IAM', 'phase_q' => 70, 'phase_time' => 85, 'lessons' => ['Network Security', 'Access Control', 'Federation & PAM']],
                        ['name' => 'Operations & Software', 'phase_q' => 70, 'phase_time' => 85, 'lessons' => ['SOC & IR', 'Disaster Recovery', 'SDLC & DevSecOps']],
                    ],
                ],
            ],
        ];
    }

    public function test_custom_catalog_builds_independent_cert_ladders_with_regions(): void
    {
        $this->seedSecurityRegion();

        $this->assertSame('USA-IT-SECURITY', \App\Models\PassimarkCertificationTrack::where('slug', 'sec')->value('region'));
        $this->assertSame('USA-IT-SECURITY', \App\Models\PassimarkCertificationTrack::where('slug', 'cissp')->value('region'));

        // SEC+: container + 12 lessons + 3 phase + 2 domain + 3 mocks + final = 22
        // CISSP (test fixture, 3 lessons/phase): container + 12 lessons + 4 phase + 2 domain + 3 mocks + final = 23
        $this->assertSame(22, PassimarkSession::where('cert_slug', 'sec')->count());
        $this->assertSame(23, PassimarkSession::where('cert_slug', 'cissp')->count());

        $lessons = ['sec' => 12, 'cissp' => 12]; // test fixture: phases * 3 lessons each
        foreach (['sec', 'cissp'] as $certSlug) {
            $sessions = PassimarkSession::where('cert_slug', $certSlug)->get();
            $this->assertSame($lessons[$certSlug], $sessions->where('phase_type', 'lesson')->count());
            $this->assertSame(1, $sessions->where('phase_type', 'cert')->count());
            $this->assertSame(1, $sessions->where('phase_type', 'final')->count());
            $this->assertSame(3, $sessions->where('phase_type', 'mock')->count());
        }
        // First lesson of phase 1 is open; everything else is gated behind pass/approval.
        $lesson1 = PassimarkSession::where('cert_slug', 'sec')->where('phase_type', 'lesson')->orderBy('order')->first();
        $this->assertTrue((bool) $lesson1->is_open);
        // Only the cert container + first lesson are open; phases/domains/mocks/finals stay gated.
        $this->assertSame(0, PassimarkSession::where('cert_slug', 'sec')
            ->where('is_open', true)
            ->whereNotIn('phase_type', ['lesson', 'cert'])
            ->count());

        // Demo learner is enrolled in lesson 1 only within each cert.
        $this->assertSame(2, PassimarkProgress::count());
        $this->assertDatabaseHas('passimark_progress', ['user_id' => $this->student()->id, 'session_id' => $lesson1->id, 'status' => 'open']);
    }

    public function test_every_session_ships_three_well_formed_exam_modes(): void
    {
        $this->seedSecurityRegion();

        foreach (PassimarkSession::all() as $session) {
            $this->assertCount(3, $session->exams, "session {$session->id} exam count");
            $this->assertEqualsCanonicalizing(['cat', 'timed', 'practice'], $session->exams->pluck('mode')->all());
            foreach ($session->exams as $exam) {
                $this->assertSame($session->questions_target, $exam->question_count);
                if ($exam->mode === 'cat') {
                    $this->assertTrue((bool) $exam->irt_enabled);
                    $expected = $session->time_minutes === 0 ? 0 : (int) round($session->time_minutes * 1.5);
                    $this->assertSame($expected, $exam->time_minutes);
                } else {
                    $this->assertFalse((bool) $exam->irt_enabled);
                }
                if ($exam->mode === 'practice') {
                    $this->assertSame(0, $exam->time_minutes);
                }
                if ($exam->mode === 'timed') {
                    $this->assertSame($session->time_minutes, $exam->time_minutes);
                }
            }
        }

        $final = PassimarkSession::where('cert_slug', 'cissp')->where('phase_type', 'final')->first();
        $this->assertSame(150, $final->exams->where('mode', 'cat')->first()->question_count);
        $this->assertTrue((bool) $final->exams->first()->is_final);

        // Mocks scale 70/100/120% of the final, timed per-seconds 90/180 slope.
        $mocks = PassimarkSession::where('cert_slug', 'sec')->where('phase_type', 'mock')->orderBy('order')->get();
        $this->assertSame([63, 90, 108], $mocks->pluck('questions_target')->all());
    }

    public function test_locked_sessions_are_blocked_and_passing_lesson_auto_unlocks_the_next(): void
    {
        $this->seedSecurityRegion();

        $student = $this->student();
        $lesson1 = PassimarkSession::where('cert_slug', 'sec')->where('phase_type', 'lesson')->orderBy('order')->first();
        $lesson2 = PassimarkSession::where('cert_slug', 'sec')->where('phase_type', 'lesson')->orderBy('order')->skip(1)->first();

        $this->assertNull(PassimarkProgress::where('user_id', $student->id)->where('session_id', $lesson2->id)->first());
        $this->actingAs($student)->post("/passimark/session/{$lesson2->id}/start")->assertStatus(404);

        // Give lesson 1 a real pool so the ladder can be climbed.
        for ($i = 1; $i <= 25; $i++) {
            PassimarkQuestion::create([
                'session_id' => $lesson1->id,
                'exam_id' => null,
                'content' => "L1Q{$i}: Which of the following best describes the principle?",
                'options' => [
                    ['key' => 'a', 'text' => 'Correct', 'is_correct' => true],
                    ['key' => 'b', 'text' => 'Distractor 1', 'is_correct' => false],
                    ['key' => 'c', 'text' => 'Distractor 2', 'is_correct' => false],
                    ['key' => 'd', 'text' => 'Distractor 3', 'is_correct' => false],
                ],
                'difficulty' => 0, 'discrimination' => 1.2, 'guessing' => 0.25,
                'domain' => 'SEC', 'bloom_level' => 'remember', 'correct_key' => 'a',
            ]);
        }
        // Give second lesson its own pool as well.
        for ($i = 1; $i <= 25; $i++) {
            PassimarkQuestion::create([
                'session_id' => $lesson2->id, 'exam_id' => null, 'content' => "L2Q{$i}: content", 'options' => [
                    ['key' => 'a', 'text' => 'Right', 'is_correct' => true], ['key' => 'b', 'text' => 'x', 'is_correct' => false],
                    ['key' => 'c', 'text' => 'y', 'is_correct' => false], ['key' => 'd', 'text' => 'z', 'is_correct' => false],
                ],
                'difficulty' => 0, 'discrimination' => 1.2, 'guessing' => 0.25, 'domain' => 'SEC', 'bloom_level' => 'remember', 'correct_key' => 'a',
            ]);
        }

        $this->actingAs($student)->post("/passimark/session/{$lesson1->id}/start", ['mode' => 'cat'])->assertRedirect();

        $attempt = \App\Models\PassimarkAttempt::where('user_id', $student->id)->where('session_id', $lesson1->id)->firstOrFail();
        $answered = 0;
        while ($q = CatEngine::nextQuestion($attempt->fresh())) {
            $answer = collect($q->options)->firstWhere('is_correct', true);
            $this->actingAs($student)->post("/passimark/attempt/{$attempt->id}/answer", [
                'question_id' => $q->id, 'selected' => $answer['key'],
            ])->assertOk();
            $answered++;
        }
        $this->actingAs($student)->post("/passimark/attempt/{$attempt->id}/finish")->assertOk();
        $this->assertSame(25, $answered);

        $this->assertSame('completed', PassimarkProgress::where('user_id', $student->id)->where('session_id', $lesson1->id)->value('status'));
        $this->assertDatabaseHas('passimark_progress', ['user_id' => $student->id, 'session_id' => $lesson2->id, 'status' => 'open']);
    }

    public function test_uniform_205_catalog_expands_the_static_14_session_ladder(): void
    {
        $entries = [
            ['code' => 'AWS-CP', 'name' => 'AWS Cloud Practitioner', 'track' => 'USA-CLOUD', 'final_q' => 65, 'final_time' => 90],
            ['code' => 'NCLEX-RN', 'name' => 'NCLEX-RN', 'track' => 'GLOBAL-ACADEMIC', 'final_q' => 145, 'final_time' => 300],
        ];

        (new Uniform205CatalogSeeder)->seedCatalog($entries);

        // Two uniform tracks + the default cissp track created by migration 000003.
        $this->assertSame(3, \App\Models\PassimarkCertificationTrack::count());
        $this->assertSame('GLOBAL-ACADEMIC', \App\Models\PassimarkCertificationTrack::where('slug', 'nclex-rn')->value('region'));

        foreach ($entries as $entry) {
            $sessions = PassimarkSession::where('cert_slug', strtolower($entry['code']))->orderBy('order')->get();
            $this->assertCount(14, $sessions);
            $this->assertSame(42, $sessions->sum(fn ($s) => $s->exams()->count()));

            $types = $sessions->pluck('phase_type')->all();
            $this->assertSame(
                ['cert', 'lesson', 'lesson', 'lesson', 'lesson', 'lesson', 'lesson', 'phase', 'phase', 'domain', 'mock', 'mock', 'mock', 'final'],
                $types
            );
            $this->assertSame([3, 3], $sessions->slice(7, 2)->pluck('phase')->all());
            $this->assertTrue((bool) $sessions[0]->is_open);
            $this->assertTrue((bool) $sessions[1]->is_open);
            $this->assertFalse((bool) $sessions[6]->is_open);
            $this->assertFalse((bool) $sessions[13]->is_open);
            $this->assertTrue((bool) $sessions[13]->exams->first()->is_final);
            $this->assertSame(75, $sessions[9]->pass_score);

            $lesson1Cat = $sessions[1]->exams->where('mode', 'cat')->first();
            $this->assertSame((int) round(35 * 1.5), $lesson1Cat->time_minutes);
            $this->assertTrue((bool) $lesson1Cat->irt_enabled);
            $this->assertSame(0, $sessions[1]->exams->where('mode', 'practice')->first()->time_minutes);
            $this->assertSame($entry['final_q'], $sessions[13]->questions_target);
        }
    }
}