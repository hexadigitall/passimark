<?php

namespace Tests\Feature;

use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Services\PracticeQuestionBank\UniformQuestionBankGenerator;
use Database\Seeders\Uniform205CatalogSeeder;
use Database\Seeders\Uniform205QuestionBankSeeder;
use Tests\TestCase;

class Uniform205QuestionBankSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    private function seedTinyCatalog(): void
    {
        (new Uniform205CatalogSeeder)->seedCatalog([
            ['code' => 'TST-ALPHA', 'name' => 'Test Alpha Certification', 'track' => 'USA-CLOUD', 'final_q' => 10, 'final_time' => 20],
            ['code' => 'TST-BETA', 'name' => 'Test Beta Certification', 'track' => 'AFRICA', 'final_q' => 12, 'final_time' => 25],
        ]);
    }

    public function test_fills_every_uniform_session_pool_to_its_questions_target(): void
    {
        $this->seedTinyCatalog();

        $stats = (new Uniform205QuestionBankSeeder)->seedQuestionBanks();

        $this->assertSame(2, $stats['certs']);
        $this->assertSame(28, $stats['sessions']); // 2 certs × 14-session ladder

        $sessions = PassimarkSession::all();
        $expectedTotal = $sessions->sum(fn ($s) => (int) $s->questions_target);
        $actualTotal = PassimarkQuestion::count();

        $this->assertSame($expectedTotal, $actualTotal);
        $this->assertSame($expectedTotal, $stats['questions']);
        foreach ($sessions as $session) {
            $this->assertSame(
                (int) $session->questions_target,
                PassimarkQuestion::where('session_id', $session->id)->count(),
                "session {$session->title}"
            );
        }
    }

    public function test_generated_questions_are_well_formed_and_irt_bounded(): void
    {
        $this->seedTinyCatalog();
        (new Uniform205QuestionBankSeeder)->seedQuestionBanks();

        $questions = PassimarkQuestion::all();
        $this->assertGreaterThan(0, $questions->count());

        foreach ($questions as $question) {
            $options = $question->options;
            $this->assertCount(4, $options, $question->external_id);
            $this->assertCount(1, array_filter($options, fn ($o) => $o['is_correct']), $question->external_id);
            $this->assertSame(
                collect($options)->firstWhere('is_correct', true)['key'],
                $question->correct_key,
                $question->external_id
            );
            $this->assertSame(['A', 'B', 'C', 'D'], array_column($options, 'key'), $question->external_id);

            // No item asserts a measured psychometric property. These columns were
            // NOT NULL, so the generator invented values: seeded PRNG draws for
            // difficulty and discrimination, a hardcoded 0.25 for guessing. Irt3PL
            // reads a/b/c off the row, the theta becomes pass_probability, and
            // CertificateIssuer issues on that, so the invented numbers were being
            // used to decide whether a learner earned a credential. The columns are
            // nullable now and the values are absent rather than fabricated.
            $this->assertNull($question->difficulty, $question->external_id);
            $this->assertNull($question->discrimination, $question->external_id);
            $this->assertNull($question->guessing, $question->external_id);

            $this->assertNotEmpty($question->content, $question->external_id);
            $this->assertNotEmpty($question->domain, $question->external_id);
            $this->assertNotEmpty($question->bloom_level, $question->external_id);
            $this->assertNotEmpty($question->reference, $question->external_id);
            $this->assertStringStartsWith('synth-', $question->external_id);

            // The citation must name a real body, never a document that does not exist.
            $this->assertStringNotContainsStringIgnoringCase(
                'official exam guide',
                $question->reference,
                $question->external_id
            );

            // And the item must admit what it is, since it is not drawn from a
            // real examination paper.
            $this->assertStringContainsString('Placeholder item', $question->explanation, $question->external_id);
        }
    }

    public function test_generated_questions_are_tagged_domain_and_bloom(): void
    {
        $this->seedTinyCatalog();
        (new Uniform205QuestionBankSeeder)->seedQuestionBanks();

        $this->assertGreaterThan(0, PassimarkQuestion::count());
        $this->assertSame(0, PassimarkQuestion::doesntHave('tags')->count());

        foreach (PassimarkQuestion::with('tags')->take(20)->get() as $question) {
            $types = $question->tags->pluck('type')->sort()->values()->all();
            $this->assertSame(['bloom', 'domain'], $types, $question->external_id);
        }
    }

    public function test_external_ids_are_unique(): void
    {
        $this->seedTinyCatalog();
        (new Uniform205QuestionBankSeeder)->seedQuestionBanks();

        $total = PassimarkQuestion::count();
        $this->assertSame($total, PassimarkQuestion::distinct()->count('external_id'));
        $this->assertSame(0, PassimarkQuestion::whereNull('external_id')->count());
    }

    public function test_bank_is_deterministic_and_varies_across_seeds(): void
    {
        $cert = ['code' => 'TST-ALPHA', 'name' => 'Test Alpha Certification', 'track' => 'USA-CLOUD'];
        $session = ['title' => 'Lesson 1: Foundations', 'domain' => 'TST-ALPHA-L1-FOUND', 'phase_type' => 'lesson'];

        $a = UniformQuestionBankGenerator::withSeed(12345)->question($cert, $session, 0);
        $b = UniformQuestionBankGenerator::withSeed(12345)->question($cert, $session, 0);
        $this->assertSame(md5(json_encode($a)), md5(json_encode($b)));

        $hash = fn (int $seed) => md5(json_encode(UniformQuestionBankGenerator::withSeed($seed)->question($cert, $session, 0)));
        $one = $hash(1);
        $two = $hash(2);
        $three = $hash(3);
        $this->assertNotSame($one, $two);
        $this->assertNotSame($two, $three);
    }

    public function test_seeder_is_idempotent_on_repeat_runs(): void
    {
        $this->seedTinyCatalog();

        $first = (new Uniform205QuestionBankSeeder)->seedQuestionBanks();
        $totalAfterFirst = PassimarkQuestion::count();

        $after = (new Uniform205QuestionBankSeeder)->seedQuestionBanks();

        $this->assertSame(0, $after['sessions']);
        $this->assertSame(0, $after['questions']);
        $this->assertSame($totalAfterFirst, PassimarkQuestion::count());
        $this->assertGreaterThan(0, $first['questions']);
    }
}
