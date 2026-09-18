<?php

namespace App\Services\PracticeQuestionBank;

/**
 * Deterministic generic question generator for the mass-load "Uniform 205" catalog.
 *
 * The 205-cert catalog ships only {code, name, track, final_q, final_time} — it has no
 * per-cert objectives — so, exactly like the synthesized `yu` entries in the 17 flagship
 * bank, it generates objective-mapped practice items from the session's own context
 * (title + domain + phase) rather than from cert-specific facts.
 *
 * For the same (cert, session, index, seed) the output is byte-for-byte reproducible via
 * {@see SeededRandom} (mulberry32), so pools never drift between runs or environments.
 */
final class UniformQuestionBankGenerator
{
    private const COMPANIES = ['PaySwift Ltd', 'NaijaMart', 'Lagos Logistics Co.', 'AeroTech NG', 'FinEdge Africa', 'Zaria Health Systems', 'Kano AgriTech', 'Harbourline Bank', 'Savannah Telecom'];

    private const BLOOMS = ['Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate'];

    private const ISSUES = ['performance bottleneck', 'security gap', 'compliance requirement', 'scalability challenge', 'audit finding', 'service outage'];

    public function __construct(private readonly SeededRandom $rand)
    {
    }

    public static function withSeed(int $seed): self
    {
        return new self(new SeededRandom($seed));
    }

    /**
     * Build one self-contained question for a uniform-catalog session.
     *
     * @param array{code:string,name:string,track?:string} $cert
     * @param array{title:string,domain:string,phase_type:string} $session
     * @return array{objective_reference:string,content:string,options:array<int,array<string,mixed>>,difficulty:float,discrimination:float,guessing:float,domain:string,bloom_level:string,explanation:string,reference:string}
     */
    public function question(array $cert, array $session, int $index): array
    {
        $code = (string) $cert['code'];
        $name = (string) $cert['name'];
        $rawDomain = (string) ($session['domain'] ?? $code);
        $objective = $this->objective((string) $session['phase_type'], (string) $session['title'], $name);
        $area = $this->area((string) $session['phase_type'], $name, $objective);
        $bloom = $this->rand->pick(self::BLOOMS);

        $content = $this->stem($index, $name, $objective, $area, $bloom);
        $options = $this->options($name, $objective, $rawDomain);
        $reference = "{$name} official exam guide";

        return [
            'objective_reference' => "{$code} {$rawDomain} Q".($index + 1),
            'content' => $content,
            'options' => $options,
            'difficulty' => round($this->rand->range(-1.5, 2.0), 2),
            'discrimination' => round($this->rand->range(0.8, 1.8), 2),
            'guessing' => 0.25,
            'domain' => $rawDomain,
            'bloom_level' => $bloom,
            'explanation' => "Correct because it follows {$name} guidance for {$objective}: gather requirements, apply the documented method, validate the outcome, and record the decision. The distractors skip validation, bypass governance, or disable monitoring.",
            'reference' => $reference,
        ];
    }

    private function objective(string $phaseType, string $title, string $name): string
    {
        return match ($phaseType) {
            'cert' => "complete the full {$name} certification track",
            'phase' => 'perform under cumulative timed pressure',
            'domain' => 'demonstrate full-domain mastery',
            'mock' => 'sustain accuracy under exam pressure',
            'final' => "perform at the real {$name} exam specification",
            default => $this->lessonTopic($title),
        };
    }

    private function area(string $phaseType, string $name, string $objective): string
    {
        return match ($phaseType) {
            'cert' => "the {$name} track",
            'phase', 'domain' => 'the assessed domain',
            'mock', 'final' => 'timed exam conditions',
            default => $objective,
        };
    }

    private function lessonTopic(string $title): string
    {
        $topic = preg_replace('/^Lesson\s*\d+\s*:\s*/i', '', $title) ?? $title;
        $topic = trim(preg_replace('/\s+/', ' ', $topic) ?? $topic);

        return $topic !== '' ? $topic : 'the lesson objectives';
    }

    private function stem(int $index, string $name, string $objective, string $area, string $bloom): string
    {
        $company = $this->rand->pick(self::COMPANIES);
        $issue = $this->rand->pick(self::ISSUES);
        $n = $index + 1;

        $stems = [
            "A practitioner at {$company} is responsible for {$objective} within {$area}. Which action best aligns with {$name} guidance? (Original Q{$n})",
            "Scenario {$n}: during a {$name} engagement, the team must address {$objective} and a {$issue} arises. Which approach is MOST appropriate?",
            "For \"{$objective}\" in {$area}, which option demonstrates {$bloom}-level understanding of {$name}?",
            "{$company} is implementing {$objective} as part of {$area}. Which decision follows {$name} best practice FIRST?",
            "Which practice best supports {$objective} in {$area} when preparing for the {$name} exam? (Original Q{$n})",
            "An examiner asks how to handle {$objective} under {$area}. Which response reflects {$name} guidance?",
        ];

        return $stems[$this->rand->int(0, count($stems) - 1)];
    }

    /**
     * @return array<int,array{key:string,text:string,is_correct:bool}>
     */
    private function options(string $name, string $objective, string $domain): array
    {
        $correct = $this->rand->pick([
            "Apply {$objective} using {$name} best practices: confirm requirements, implement least privilege, validate the result, and document the decision",
            "Follow the current {$name} reference guidance for {$objective}: plan, validate against requirements, monitor, and record the outcome",
            "Use the documented {$name} method for {$objective}, verify it against the stated requirements, and monitor for drift",
            "Adopt the {$name} recommended approach to {$objective}, test it in a controlled environment, then document and monitor it",
        ]);

        $distractorPool = [
            "Rely on an undocumented workaround for {$objective} to save time",
            "Use a deprecated method for {$domain} without assessing the impact",
            "Disable logging and monitoring for {$domain} to improve performance",
            "Hardcode credentials and open firewall access to finish {$objective} quickly",
            "Skip validation and deploy {$objective} straight to production to meet the deadline",
            "Assign {$objective} to one person with no review and no documentation",
            "Ignore {$name} guidance and copy an unverified third-party configuration",
            "Postpone {$objective} indefinitely because the current state is good enough",
            "Escalate {$objective} to the vendor and take no local action",
            "Make the change in production first and backfill documentation later",
        ];

        $picked = array_slice($this->rand->shuffle($distractorPool), 0, 3);
        $shuffled = $this->rand->shuffle([$correct, ...$picked]);

        $options = [];
        foreach ($shuffled as $i => $text) {
            $options[] = ['key' => chr(65 + $i), 'text' => $text, 'is_correct' => $text === $correct];
        }

        return $options;
    }
}
