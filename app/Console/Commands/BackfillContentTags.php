<?php
namespace App\Console\Commands;

use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\PassimarkTag;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillContentTags extends Command
{
    protected $signature = 'passimark:backfill-tags';

    protected $description = 'Create taxonomy tags from existing domain/bloom_level strings and attach them (idempotent, non-destructive).';

    public function handle(): int
    {
        $questionsAttached = 0;
        foreach (PassimarkQuestion::cursor() as $question) {
            $tagIds = [];
            if ($question->domain) {
                $tagIds[] = $this->ensureTag('domain', $question->domain)->id;
            }
            if ($question->bloom_level) {
                $tagIds[] = $this->ensureTag('bloom', $question->bloom_level)->id;
            }
            $tagIds = array_values(array_unique(array_filter($tagIds)));
            if ($tagIds !== []) {
                $question->tags()->syncWithoutDetaching($tagIds);
                $questionsAttached++;
            }
        }

        $sessionsAttached = 0;
        foreach (PassimarkSession::cursor() as $session) {
            if (! $session->domain) {
                continue;
            }
            $session->tags()->syncWithoutDetaching([$this->ensureTag('domain', $session->domain)->id]);
            $sessionsAttached++;
        }

        $this->info("Attached domain/bloom tags to {$questionsAttached} questions and {$sessionsAttached} sessions.");
        return self::SUCCESS;
    }

    private function ensureTag(string $type, string $label): PassimarkTag
    {
        return PassimarkTag::firstOrCreate(
            ['type' => $type, 'slug' => Str::slug($label)],
            ['label' => $label]
        );
    }
}