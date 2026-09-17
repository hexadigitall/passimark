<?php

namespace Tests\Feature;

use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Services\PracticeQuestionBank\QuestionBankGenerator;
use Database\Seeders\WorldwideOriginalQuestionBankSeeder;
use Database\Seeders\WorldwidePassimarkCatalogSeeder;
use Tests\TestCase;

class OriginalQuestionBankSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    private function seedSecurityRegion(): void
    {
        (new WorldwidePassimarkCatalogSeeder)->seedCatalog([
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
                    ],
                ],
            ],
        ]);
    }

    public function test_fills_every_worldwide_session_pool_to_its_questions_target(): void
    {
        $this->seedSecurityRegion();

        $stats = (new WorldwideOriginalQuestionBankSeeder)->seedQuestionBanks();

        $this->assertSame(1, $stats['certs']); // CISSP is intentionally skipped
        $this->assertSame(22, $stats['sessions']); // SEC+: cert + 12 lessons + 3 phases + 2 domains + 3 mocks + final
        $sessions = PassimarkSession::where('cert_slug', 'sec')->get();
        $expectedTotal = $sessions->sum(fn ($s) => (int) $s->questions_target);
        $actualTotal = PassimarkQuestion::whereIn('session_id', $sessions->pluck('id'))->count();

        $this->assertSame($expectedTotal, $actualTotal);
        foreach ($sessions as $session) {
            $this->assertSame(
                (int) $session->questions_target,
                PassimarkQuestion::where('session_id', $session->id)->count(),
                "session {$session->title}"
            );
        }
        $this->assertSame($expectedTotal, $stats['questions']);
    }

    public function test_cissp_is_skipped_because_it_ships_its_own_bundle(): void
    {
        $this->seedSecurityRegion();

        (new WorldwideOriginalQuestionBankSeeder)->seedQuestionBanks();

        $this->assertSame(0, PassimarkQuestion::whereIn(
            'session_id',
            PassimarkSession::where('cert_slug', 'cissp')->pluck('id')
        )->count());
    }

    public function test_generated_questions_are_well_formed_and_irt_bounded(): void
    {
        $this->seedSecurityRegion();
        (new WorldwideOriginalQuestionBankSeeder)->seedQuestionBanks();

        $questions = PassimarkQuestion::query()
            ->whereIn('session_id', PassimarkSession::where('cert_slug', 'sec')->pluck('id'))
            ->get();

        $this->assertGreaterThan(0, $questions->count());

        foreach ($questions as $question) {
            $options = $question->options;
            $this->assertCount(4, $options, $question->external_id);
            $this->assertCount(1, array_filter($options, fn ($o) => $o['is_correct']));
            $this->assertSame(
                collect($options)->firstWhere('is_correct', true)['key'],
                $question->correct_key,
                $question->external_id
            );
            $voices = array_column($options, 'key');
            $this->assertSame(['A', 'B', 'C', 'D'], $voices, $question->external_id);

            $this->assertGreaterThanOrEqual(-3, $question->difficulty, $question->external_id);
            $this->assertLessThanOrEqual(3, $question->difficulty, $question->external_id);
            $this->assertGreaterThanOrEqual(0.1, $question->discrimination, $question->external_id);
            $this->assertLessThanOrEqual(3, $question->discrimination, $question->external_id);
            $this->assertSame(0.25, $question->guessing, $question->external_id);

            $this->assertNotEmpty($question->content, $question->external_id);
            $this->assertNotEmpty($question->domain, $question->external_id);
            $this->assertNotEmpty($question->bloom_level, $question->external_id);
            $this->assertNotEmpty($question->reference, $question->external_id);
            $this->assertNotNull($question->external_id);
        }
    }

    public function test_generated_questions_are_tagged_domain_and_bloom(): void
    {
        $this->seedSecurityRegion();
        (new WorldwideOriginalQuestionBankSeeder)->seedQuestionBanks();

        $questions = PassimarkQuestion::query()
            ->whereIn('session_id', PassimarkSession::where('cert_slug', 'sec')->pluck('id'))
            ->get();

        $this->assertGreaterThan(0, $questions->count());
        $this->assertSame(0, PassimarkQuestion::doesntHave('tags')->count());
        foreach ($questions->take(20) as $question) {
            $types = $question->tags->pluck('type')->sort()->values()->all();
            $this->assertSame(['bloom', 'domain'], $types, $question->external_id);
        }
    }

    public function test_bank_is_deterministic_for_the_same_seed(): void
    {
        $cert = QuestionBankGenerator::withSeed(0)->cert('SAA-C03');

        $a = QuestionBankGenerator::withSeed(12345)->question($cert, 0);
        $b = QuestionBankGenerator::withSeed(12345)->question($cert, 0);

        $this->assertSame(md5(json_encode($a)), md5(json_encode($b)));
    }

    public function test_bank_varies_across_seeds(): void
    {
        $cert = QuestionBankGenerator::withSeed(0)->cert('SAA-C03');
        $one = QuestionBankGenerator::withSeed(1)->question($cert, 0);
        $two = QuestionBankGenerator::withSeed(2)->question($cert, 0);
        $three = QuestionBankGenerator::withSeed(3)->question($cert, 0);

        $m = fn (array $q) => md5(json_encode([$q['content'], $q['options']]));
        $this->assertNotSame($m($one), $m($two));
        $this->assertNotSame($m($two), $m($three));
    }

    public function test_seeder_is_idempotent_on_repeat_runs(): void
    {
        $this->seedSecurityRegion();

        $first = (new WorldwideOriginalQuestionBankSeeder)->seedQuestionBanks();
        $totalAfterFirst = PassimarkQuestion::count();

        $after = (new WorldwideOriginalQuestionBankSeeder)->seedQuestionBanks();
        $totalAfterSecond = PassimarkQuestion::count();

        $this->assertSame(0, $after['sessions']);
        $this->assertSame(0, $after['questions']);
        $this->assertSame($totalAfterFirst, $totalAfterSecond);
    }

    public function test_every_catalog_cert_generates_valid_variant_questions(): void
    {
        $catalog = QuestionBankGenerator::withSeed(0)->catalog();
        $generator = QuestionBankGenerator::withSeed(0);
        foreach ($catalog as $code => $cert) {
            for ($i = 0; $i < 10; $i++) {
                $q = $generator->question($cert, $i);
                $this->assertCount(4, $q['options'], "{$code}#{$i}");
                $this->assertCount(1, array_filter($q['options'], fn ($o) => $o['is_correct']), "{$code}#{$i}");
                $this->assertSame(0.25, $q['guessing'], "{$code}#{$i}");
                $this->assertNotEmpty($q['content'], "{$code}#{$i}");
            }
        }
    }
}