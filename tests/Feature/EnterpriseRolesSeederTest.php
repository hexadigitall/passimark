<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use Database\Seeders\EnterpriseRolesSeeder;
use Tests\TestCase;

class EnterpriseRolesSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
        $this->seed(EnterpriseRolesSeeder::class);
    }

    private function tracks(): \Illuminate\Support\Collection
    {
        return PassimarkCertificationTrack::where('source', 'seed:enterprise-roles')->get();
    }

    public function test_it_creates_five_role_tracks(): void
    {
        $this->assertCount(5, $this->tracks());

        foreach ($this->tracks() as $track) {
            $this->assertNotNull($track->cert_key);
            $this->assertSame(0, PassimarkSession::where('certification_track_id', $track->id)
                ->where('theta_required', '!=', null)->count());
        }
    }

    /**
     * The source artifact keyed 66 of 66 items to option A. If the rotation regresses, a
     * learner can pass the whole bank without reading anything, so this is the guard that
     * matters most in this seeder.
     */
    public function test_answer_keys_are_spread_across_all_four_options(): void
    {
        $questions = PassimarkQuestion::all();
        $this->assertGreaterThanOrEqual(60, $questions->count());

        $dist = [];
        foreach ($questions as $q) {
            $key = $q->correct_key;
            $dist[$key] = ($dist[$key] ?? 0) + 1;

            $correct = collect($q->options)->firstWhere('is_correct', true);
            $this->assertNotNull($correct, "item {$q->id} has no option flagged correct");
            $this->assertSame($key, $correct['key'], "correct_key disagrees with the flagged option");
        }

        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $this->assertGreaterThan(0, $dist[$letter] ?? 0, "no item is keyed {$letter}");
        }

        $maxShare = max($dist) / array_sum($dist);
        $this->assertLessThanOrEqual(
            0.40,
            $maxShare,
            'one answer letter still dominates ('.json_encode($dist).') - the learner can game it'
        );
    }

    /**
     * The artifact synthesised difficulty from a -1.5..2.0 range, discrimination from
     * 0.8..1.8 and a flat 0.25 guessing floor. Irt3PL reads these to drive the adaptive
     * engine, so an invented value silently steers every learner's path.
     */
    public function test_no_item_claims_a_psychometric_parameter(): void
    {
        foreach (PassimarkQuestion::all() as $q) {
            $this->assertNull($q->difficulty, "item {$q->id} asserts difficulty");
            $this->assertNull($q->discrimination, "item {$q->id} asserts discrimination");
            $this->assertNull($q->guessing, "item {$q->id} asserts guessing");
        }
    }

    public function test_no_session_declares_an_invented_pass_bar(): void
    {
        foreach (PassimarkSession::all() as $s) {
            $this->assertNull($s->theta_required, "session {$s->id} declares a theta bar");
            $this->assertNotNull($s->pass_score, 'lesson progression still needs pass_score');
        }
    }

    /**
     * The artifact advertised 4,965 questions from 66 templates. Session sizes must reflect
     * the items that actually exist, so nothing here repeats content to hit a target.
     */
    public function test_session_sizes_are_derived_from_real_items(): void
    {
        foreach (PassimarkSession::all() as $session) {
            $available = PassimarkQuestion::where('session_id', $session->id)->count();
            $this->assertSame(
                $available,
                (int) $session->question_count,
                "session {$session->id} advertises {$session->question_count} questions but holds {$available}"
            );
            $this->assertLessThanOrEqual(
                15,
                $available,
                "session {$session->id} holds {$available} items - that is inflated for a 66-item bank"
            );
        }

        // 66 authored items, each stored once. The final assessment reuses the domain pool
        // by reference, not by duplicating rows.
        $this->assertSame(66, PassimarkQuestion::count());
    }

    public function test_every_item_keeps_its_vendor_citation(): void
    {
        foreach (PassimarkQuestion::all() as $q) {
            $this->assertNotEmpty($q->reference, "item {$q->id} has no reference");
            $this->assertStringContainsString('Authored practice item', $q->reference);
            $this->assertMatchesRegularExpression(
                '/Microsoft Learn|ITIL|Cisco|CompTIA|Microsoft 365|RFC\s?\d|Wi-?Fi Alliance|Wireshark/i',
                $q->reference,
                "item {$q->id} does not cite a real source: {$q->reference}"
            );
        }
    }

    public function test_every_track_is_startable_after_seeding(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        foreach ($this->tracks() as $track) {
            $first = PassimarkSession::where('certification_track_id', $track->id)
                ->where('is_open', true)->first();
            $this->assertNotNull($first, "track {$track->slug} has no open session");

            $this->assertTrue(
                PassimarkProgress::where('user_id', $student->id)->where('session_id', $first->id)->exists(),
                "track {$track->slug} is unstartable: no open progress row for session 1"
            );
        }
    }

    public function test_reseeding_is_idempotent(): void
    {
        $tracks = $this->tracks()->count();
        $questions = PassimarkQuestion::count();
        $sessions = PassimarkSession::count();

        $this->seed(EnterpriseRolesSeeder::class);

        $this->assertSame($tracks, $this->tracks()->count(), 'reseeding created duplicate tracks');
        $this->assertSame($questions, PassimarkQuestion::count());
        $this->assertSame($sessions, PassimarkSession::count());
    }

    public function test_it_does_not_touch_the_cissp_bundle(): void
    {
        $this->seed(\Database\Seeders\CISSPBundleSeeder::class);

        $cissp = PassimarkCertificationTrack::where('slug', 'cissp')->firstOrFail();
        $this->assertSame(46, PassimarkSession::where('certification_track_id', $cissp->id)->count());
        $this->assertSame(1025, PassimarkQuestion::whereIn(
            'session_id',
            PassimarkSession::where('certification_track_id', $cissp->id)->select('id')
        )->count());
    }
}
