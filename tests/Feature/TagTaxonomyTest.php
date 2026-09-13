<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\PassimarkTag;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TagTaxonomyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
        $this->seed(PassimarkSeeder::class);
    }

    public function test_admin_module_loads_tags_for_the_taxonomy_screen(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();

        $this->actingAs($admin)->get('/admin/passimark')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Admin')
                ->has('tags'));
    }

    public function test_admin_can_create_rename_and_delete_tags_and_students_are_blocked(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();

        $this->actingAs($student)->get('/admin/passimark')->assertForbidden();
        $this->actingAs($student)->postJson('/admin/tags', ['type' => 'domain', 'label' => 'Insider Threat'])->assertForbidden();

        $response = $this->actingAs($admin)->postJson('/admin/tags', ['type' => 'domain', 'label' => 'Insider Threat'])->assertCreated();
        $tagId = $response->json('data.id');
        $this->assertDatabaseHas('passimark_tags', ['id' => $tagId, 'type' => 'domain', 'slug' => 'insider-threat']);

        $this->actingAs($admin)->postJson('/admin/tags', ['type' => 'domain', 'label' => 'Insider Threat'])->assertStatus(422);
        $this->actingAs($admin)->postJson('/admin/tags', ['type' => 'planet', 'label' => 'Nope'])->assertStatus(422);

        $this->actingAs($admin)->putJson("/admin/tags/{$tagId}", ['label' => 'Insider Risk'])->assertOk();
        $this->assertDatabaseHas('passimark_tags', ['id' => $tagId, 'label' => 'Insider Risk', 'slug' => 'insider-risk']);

        $this->actingAs($admin)->deleteJson("/admin/tags/{$tagId}")->assertNoContent();
        $this->assertDatabaseMissing('passimark_tags', ['id' => $tagId]);
    }

    public function test_tags_can_be_attached_to_sessions_and_questions_and_are_validated(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();
        $domain = $this->actingAs($admin)->postJson('/admin/tags', ['type' => 'domain', 'label' => 'Zero Trust'])->assertCreated()->json('data');
        $skill = $this->actingAs($admin)->postJson('/admin/tags', ['type' => 'skill', 'label' => 'Risk Communication'])->assertCreated()->json('data');

        $sessionResponse = $this->actingAs($admin)->postJson('/admin/sessions', [
            'certification_track_id' => 1, 'number' => 50, 'phase' => 3, 'title' => 'Tagged session', 'order' => 50,
            'tag_ids' => [$domain['id']],
        ])->assertCreated();
        $sessionId = $sessionResponse->json('data.id');
        $this->assertDatabaseHas('passimark_session_tag', ['session_id' => $sessionId, 'tag_id' => $domain['id']]);

        $questionResponse = $this->actingAs($admin)->postJson('/admin/questions', [
            'session_id' => $sessionId, 'content' => 'Which assumption underpins a zero-trust architecture?',
            'tag_ids' => [$domain['id'], $skill['id']],
            'options' => [
                ['key' => 'A', 'text' => 'Never trust, always verify', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Trust by default', 'is_correct' => false],
            ],
        ])->assertCreated();
        $questionId = $questionResponse->json('data.id');
        $this->assertDatabaseHas('passimark_question_tag', ['question_id' => $questionId, 'tag_id' => $domain['id']]);
        $this->assertDatabaseHas('passimark_question_tag', ['question_id' => $questionId, 'tag_id' => $skill['id']]);

        $this->actingAs($admin)->putJson("/admin/questions/{$questionId}", ['tag_ids' => [$skill['id']]])->assertOk();
        $this->assertDatabaseMissing('passimark_question_tag', ['question_id' => $questionId, 'tag_id' => $domain['id']]);
        $this->assertDatabaseHas('passimark_question_tag', ['question_id' => $questionId, 'tag_id' => $skill['id']]);

        $this->actingAs($admin)->postJson('/admin/sessions', [
            'certification_track_id' => 1, 'number' => 51, 'phase' => 3, 'title' => 'Bad tag session', 'order' => 51,
            'tag_ids' => [99999],
        ])->assertStatus(422);
        $this->actingAs($admin)->postJson('/admin/questions', [
            'session_id' => $sessionId, 'content' => 'Invalid tag', 'tag_ids' => [99999],
            'options' => [['key' => 'A', 'text' => 'Yes', 'is_correct' => true], ['key' => 'B', 'text' => 'No', 'is_correct' => false]],
        ])->assertStatus(422);
    }

    public function test_deleting_a_tag_that_is_in_use_is_blocked(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();
        $tag = PassimarkTag::create(['type' => 'domain', 'label' => 'Usage Test', 'slug' => Str::slug('Usage Test')]);

        $question = PassimarkQuestion::create([
            'session_id' => 1, 'content' => 'Used question', 'options' => [['key' => 'A', 'text' => 'Yes', 'is_correct' => true]],
            'difficulty' => 0, 'discrimination' => 1.0, 'guessing' => 0.25, 'domain' => 'Usage Test',
        ]);
        $question->tags()->attach($tag->id);

        $this->actingAs($admin)->deleteJson("/admin/tags/{$tag->id}")->assertStatus(422);
        $this->assertDatabaseHas('passimark_tags', ['id' => $tag->id]);

        $question->delete();
        $this->actingAs($admin)->deleteJson("/admin/tags/{$tag->id}")->assertNoContent();
    }

    public function test_backfill_command_creates_tags_from_legacy_strings_without_data_loss(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();

        $session = PassimarkSession::create([
            'certification_track_id' => 1, 'number' => 60, 'phase' => 4, 'title' => 'Legacy session', 'order' => 60,
            'domain' => 'Legacy Domain X',
        ]);
        $this->assertDatabaseMissing('passimark_session_tag', ['session_id' => $session->id]);

        $this->artisan('passimark:backfill-tags')->assertSuccessful();
        $tag = PassimarkTag::where('type', 'domain')->where('slug', 'legacy-domain-x')->firstOrFail();
        $this->assertDatabaseHas('passimark_session_tag', ['session_id' => $session->id, 'tag_id' => $tag->id]);

        $seededTagCount = PassimarkTag::count();
        $question = PassimarkQuestion::where('session_id', 1)->firstOrFail();
        $legacyPivotCount = $question->tags()->count();

        $this->artisan('passimark:backfill-tags')->assertSuccessful();
        $this->assertSame($seededTagCount, PassimarkTag::count(), 'Backfill must be idempotent and never create duplicates.');
        $this->assertSame($legacyPivotCount, $question->tags()->count());

        $this->assertDatabaseCount('passimark_tags', $seededTagCount);
        $this->assertSame($session->fresh()->domain, 'Legacy Domain X', 'Legacy string columns must be preserved.');
    }

    public function test_question_creation_without_domain_string_still_creates_with_tags(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();
        $domain = $this->actingAs($admin)->postJson('/admin/tags', ['type' => 'domain', 'label' => 'Supply Chain'])->assertCreated()->json('data');

        $response = $this->actingAs($admin)->postJson('/admin/questions', [
            'session_id' => 1, 'content' => 'Tag-only question with no legacy domain string.',
            'tag_ids' => [$domain['id']],
            'options' => [
                ['key' => 'A', 'text' => 'Correct', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Incorrect', 'is_correct' => false],
            ],
        ])->assertCreated();

        $questionId = $response->json('data.id');
        $this->assertDatabaseHas('passimark_question_tag', ['question_id' => $questionId, 'tag_id' => $domain['id']]);
    }
}