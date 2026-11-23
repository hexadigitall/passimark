<?php

namespace Tests\Unit;

use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\PassimarkSpacedRepetition;
use App\Models\User;
use App\Services\SpacedRepetition\LeitnerEngine;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeitnerEngineTest extends TestCase
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

    public function test_correct_attempt_advances_box_and_schedules_longer_interval(): void
    {
        $engine = new LeitnerEngine();
        $user = $this->student();
        $question = PassimarkQuestion::firstOrFail();

        // 1st correct answer -> advances to Box 2
        $record1 = $engine->recordAttempt($user->id, $question->id, true);
        $this->assertEquals(2, $record1->box);
        $this->assertEquals(1, $record1->consecutive_correct);
        $this->assertTrue($record1->next_review_at->isAfter(now()->addHours(70)));

        // 2nd correct answer -> advances to Box 3
        $record2 = $engine->recordAttempt($user->id, $question->id, true);
        $this->assertEquals(3, $record2->box);
        $this->assertEquals(2, $record2->consecutive_correct);
        $this->assertTrue($record2->next_review_at->isAfter(now()->addDays(6)));
    }

    public function test_incorrect_attempt_drops_back_to_box_1_immediately(): void
    {
        $engine = new LeitnerEngine();
        $user = $this->student();
        $question = PassimarkQuestion::firstOrFail();

        // Advance to Box 4
        $engine->recordAttempt($user->id, $question->id, true);
        $engine->recordAttempt($user->id, $question->id, true);
        $record = $engine->recordAttempt($user->id, $question->id, true);
        $this->assertEquals(4, $record->box);

        // Fail question -> drops back to Box 1
        $failedRecord = $engine->recordAttempt($user->id, $question->id, false);
        $this->assertEquals(1, $failedRecord->box);
        $this->assertEquals(0, $failedRecord->consecutive_correct);
    }

    public function test_box_caps_at_maximum_mastery_level_5(): void
    {
        $engine = new LeitnerEngine();
        $user = $this->student();
        $question = PassimarkQuestion::firstOrFail();

        for ($i = 0; $i < 10; $i++) {
            $record = $engine->recordAttempt($user->id, $question->id, true);
        }

        $this->assertEquals(5, $record->box);
        $this->assertEquals(10, $record->consecutive_correct);
    }
}
