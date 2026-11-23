<?php

namespace Tests\Feature;

use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\PassimarkSpacedRepetition;
use App\Models\User;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpacedRepetitionAndAiTutorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PassimarkSeeder::class);
    }

    private function student(): User
    {
        return User::where('email', 'student@passimark.com')->firstOrFail();
    }

    public function test_daily_drill_endpoint_delivers_sanitized_questions(): void
    {
        $user = $this->student();
        $question = PassimarkQuestion::firstOrFail();

        // Seed a spaced repetition review due now
        PassimarkSpacedRepetition::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'box' => 1,
            'consecutive_correct' => 0,
            'review_count' => 1,
            'next_review_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/drill/daily?limit=5');

        $response->assertOk();
        $response->assertJsonStructure([
            'count',
            'questions' => [
                '*' => ['id', 'content', 'options'],
            ],
        ]);

        // Invariant 3: Zero Answer Leakage in candidate payload
        $first = $response->json('questions.0');
        $this->assertArrayNotHasKey('correct_key', $first);
        $this->assertArrayNotHasKey('is_correct', $first);
        foreach ($first['options'] as $opt) {
            $this->assertArrayNotHasKey('is_correct', $opt);
        }
    }

    public function test_spaced_repetition_stats_reports_box_breakdown(): void
    {
        $user = $this->student();
        $questions = PassimarkQuestion::take(3)->get();

        PassimarkSpacedRepetition::create([
            'user_id' => $user->id,
            'question_id' => $questions[0]->id,
            'box' => 1,
            'next_review_at' => now()->subHour(),
        ]);
        PassimarkSpacedRepetition::create([
            'user_id' => $user->id,
            'question_id' => $questions[1]->id,
            'box' => 3,
            'next_review_at' => now()->addDays(5),
        ]);
        PassimarkSpacedRepetition::create([
            'user_id' => $user->id,
            'question_id' => $questions[2]->id,
            'box' => 5,
            'next_review_at' => now()->addDays(20),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/drill/stats');

        $response->assertOk();
        $response->assertJson([
            'box_1' => 1,
            'box_2' => 0,
            'box_3' => 1,
            'box_4' => 0,
            'box_5' => 1,
            'total_tracked' => 3,
            'mastered_count' => 1,
            'due_for_review' => 1,
        ]);
    }

    public function test_ai_tutor_explains_distractor_with_conceptual_rationale(): void
    {
        $user = $this->student();
        $question = PassimarkQuestion::firstOrFail();
        if (empty($question->options)) {
            $question->options = [
                ['key' => 'A', 'text' => 'Role-Based Access Control', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Discretionary Access Control', 'is_correct' => false],
            ];
        }
        $question->correct_key = 'A';
        $question->save();

        $options = $question->options;
        $correctKey = $question->correct_key;

        // Choose a genuine distractor
        $distractor = collect($options)->first(fn ($opt) => strcasecmp((string) $opt['key'], (string) $correctKey) !== 0);
        $this->assertNotNull($distractor);

        $response = $this->actingAs($user)
            ->postJson('/api/tutor/explain', [
                'question_id' => $question->id,
                'chosen_key' => $distractor['key'],
            ]);

        $response->assertOk();
        $response->assertJson([
            'is_correct' => false,
            'chosen' => [
                'key' => $distractor['key'],
            ],
            'correct' => [
                'key' => $correctKey,
            ],
        ]);
        $this->assertNotEmpty($response->json('why_chosen_is_incorrect'));
        $this->assertNotEmpty($response->json('why_correct_is_best'));
        $this->assertNotEmpty($response->json('key_takeaway'));
    }
}
