<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use Database\Seeders\CISSPBundleSeeder;
use Tests\TestCase;

class DemoLearnerResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
        $this->seed(CISSPBundleSeeder::class);
    }

    private function student(): User
    {
        return User::where('email', 'student@passimark.com')->firstOrFail();
    }

    public function test_reset_leaves_the_learner_on_a_usable_first_run(): void
    {
        $student = $this->student();

        // The bundle seeder opens session 1; pretend the learner has been through it.
        $entry = PassimarkSession::where('number', 1)->firstOrFail();
        PassimarkProgress::where('user_id', $student->id)->update(['status' => 'completed']);

        $this->artisan('passimark:demo-reset', ['--force' => true])->assertSuccessful();

        // Preferences are cleared, which is what re-arms the first-run funnel.
        $this->assertNull($student->fresh()->preferences);

        // ...but the learner must still have somewhere to go. The ladder only starts a
        // session that has an open progress row, so an empty progress table would leave
        // every track unstartable. Deleting progress has to re-open the entry point.
        $open = PassimarkProgress::where('user_id', $student->id)
            ->where('status', 'open')->get();

        $this->assertGreaterThan(0, $open->count(), 'reset left the learner with no startable session');

        $firstAssessable = PassimarkSession::where('question_count', '>', 0)
            ->where('is_optional', false)->orderBy('order')->firstOrFail();

        $this->assertTrue(
            $open->contains('session_id', $firstAssessable->id),
            'reset did not re-open the first assessable session'
        );

        // Nothing may be left mid-flight.
        $this->assertSame(0, PassimarkProgress::where('user_id', $student->id)
            ->whereIn('status', ['in_progress', 'failed'])->count());
    }

    public function test_reset_does_not_touch_the_question_bank(): void
    {
        $before = PassimarkQuestion::count();

        $this->artisan('passimark:demo-reset', ['--force' => true])->assertSuccessful();

        $this->assertSame($before, PassimarkQuestion::count());
    }

    public function test_reset_is_idempotent(): void
    {
        $this->artisan('passimark:demo-reset', ['--force' => true])->assertSuccessful();
        $after = PassimarkProgress::where('user_id', $this->student()->id)->count();

        $this->artisan('passimark:demo-reset', ['--force' => true])->assertSuccessful();

        $this->assertSame($after, PassimarkProgress::where('user_id', $this->student()->id)->count());
    }
}
