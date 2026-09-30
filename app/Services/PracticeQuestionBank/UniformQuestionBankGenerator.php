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
    /**
     * Field-neutral situations.
     *
     * The previous list was security/operations flavoured ("security gap",
     * "service outage") and was spliced into items for a French baccalaureat,
     * NCLEX-RN and ACCA alike. These read coherently in any field.
     */
    private const ISSUES = [
        'a tight deadline',
        'an unclear requirement',
        'conflicting stakeholder input',
        'limited time remaining',
        'a quality concern',
        'a scheduling constraint',
    ];

    private const BLOOMS = ['Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate'];

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
        $topic = $this->lessonTopic((string) ($session['title'] ?? ''));
        $objective = $this->objective((string) $session['phase_type'], $topic, $name);
        $area = $this->area((string) $session['phase_type'], $topic, $name);
        $bloom = $this->rand->pick(self::BLOOMS);

        $content = $this->stem($index, $name, $objective, $area, $bloom);
        $options = $this->options($name, $objective, $rawDomain);
        $reference = self::referenceFor($cert);

        return [
            // Prefixed so a synthetic item is traceable in the data. These are
            // not items drawn from any real examination paper.
            'objective_reference' => "SYNTH {$code} {$rawDomain} Q".($index + 1),
            'content' => $content,
            'options' => $options,
            // Left null on purpose. These were seeded PRNG draws, and the CAT
            // reads them: Irt3PL::params() takes a/b/c straight off the row, the
            // resulting theta lands on progress as pass_probability, and
            // CertificateIssuer issues the credential from that. A random number
            // is not a measurement, so asserting one produced a plausible-looking
            // ability estimate and a certificate backed by nothing. Null makes
            // Irt3PL fall back to its documented defaults, which are visibly
            // defaults rather than invented data.
            'difficulty' => null,
            'discrimination' => null,
            'guessing' => null,
            'domain' => $rawDomain,
            'bloom_level' => $bloom,
            'explanation' => 'Placeholder item. It exercises the delivery and scoring path for '
                ."{$name} but is not drawn from a real examination paper, and no measured "
                .'item parameters exist for it, so no ability or pass estimate from this item '
                .'should be trusted.',
            'reference' => $reference,
        ];
    }

    /**
     * Cite the real awarding body, and the real published blueprint where one is
     * known to exist.
     *
     * This used to return "<cert name> official exam guide" for every item. No
     * such document exists for any of these examinations, so the citation named
     * a real exam's blueprint while pointing at nothing — on 167k generated
     * items that is a fabricated source, not a formatting nit. Where the exact
     * document title is not certain the citation names the awarding body and
     * stops, which is checkable, rather than inventing a plausible title.
     *
     * Static and public so the repair command that backfills existing rows
     * composes citations exactly as a fresh seed would.
     *
     * @param  array<string,mixed>  $cert
     */
    public static function referenceFor(array $cert): string
    {
        $name = (string) ($cert['name'] ?? '');
        $regulator = trim((string) ($cert['regulator'] ?? ''));
        $blueprint = trim((string) ($cert['blueprint'] ?? ''));

        if ($regulator !== '' && $blueprint !== '') {
            return "{$regulator} - {$blueprint}";
        }

        if ($regulator !== '') {
            return "Issued by {$regulator}";
        }

        return "{$name} - specification not mapped; verify with the awarding body";
    }

    /**
     * Every objective is a verb phrase, because the stems interpolate it after
     * "must" and "asked to". The four non-lesson objectives used to be activity
     * labels ("sustain accuracy under exam pressure", "demonstrate full-domain
     * mastery") which produced ungrammatical stems such as "An examiner asks
     * how to handle sustain accuracy under exam pressure under timed exam
     * conditions" - 22,201 items, the single most duplicated wording in the bank
     * at 40,902 repeats.
     */
    /**
     * Objective and area must not restate each other: the stems interpolate both
     * into one sentence, so an objective that already contains the area produces
     * "work through Foundations under the Foundations material" and "revise one
     * assessed domain to mastery under the assessed domain".
     */
    private function objective(string $phaseType, string $topic, string $name): string
    {
        return match ($phaseType) {
            'cert' => "review the full {$name} specification",
            'phase' => 'work accurately',
            'domain' => 'apply the material',
            'mock' => 'maintain accuracy',
            'final' => "perform to the {$name} standard",
            default => 'work through '.$topic,
        };
    }

    private function area(string $phaseType, string $topic, string $name): string
    {
        return match ($phaseType) {
            'cert' => "the {$name} track",
            'phase', 'domain' => 'the assessed domain',
            'mock', 'final' => 'timed exam conditions',
            default => 'the current session',
        };
    }

    private function lessonTopic(string $title): string
    {
        $topic = preg_replace('/^Lesson\s*\d+\s*:\s*/i', '', $title) ?? $title;
        $topic = trim(preg_replace('/\s+/', ' ', $topic) ?? $topic);

        return $topic !== '' ? $topic : 'the lesson objectives';
    }

    /**
     * Stems no longer contain the word "guidance" and no longer name an invented
     * company.
     *
     * Two defects fixed here. The stem used to ask "which action best aligns
     * with {$name} guidance" while the correct option read "using {$name} best
     * practices" - the answer was printed in the question, on 54,310 items. And
     * the stem used to place a fabricated company ("Kano AgriTech",
     * "PaySwift Ltd") as a real organisation on 54,261 items, inside items
     * attached to real examinations.
     */
    /**
     * Every frame is suffixed with the item number.
     *
     * Six frames over five bloom levels and six situations cannot fill the ~780
     * slots a track has, so frames carrying no varying element repeated verbatim:
     * the most common stem appeared 9,298 times, and a candidate revising would
     * meet the identical question thousands of times. The number makes each slot
     * distinct and reads for what it is - a numbered placeholder position.
     */
    private function stem(int $index, string $name, string $objective, string $area, string $bloom): string
    {
        $issue = $this->rand->pick(self::ISSUES);
        $n = $index + 1;

        $frames = [
            "A practitioner is asked to {$objective} as part of {$area}, and {$issue} is already a concern. Which action is the most defensible first step?",
            "Scenario: during a {$name} revision block the team must {$objective}, and {$issue} arises. Which response is most appropriate?",
            "Within {$area}, which option best demonstrates {$bloom}-level understanding of {$name}?",
            "A team must {$objective} within {$area}. Which decision shows {$bloom}-level judgement?",
            "Which practice best advances {$objective} in {$area} while preparing for {$name}?",
            "A candidate must {$objective} during {$area}. Which response shows {$bloom}-level judgement?",
        ];

        return $frames[$this->rand->int(0, count($frames) - 1)]." (Item {$n})";
    }

    /**
     * One correct option, not four interchangeable ones.
     *
     * The old pool offered four "correct" phrasings of the same sentence -
     * "Apply/Follow/Use/Adopt {objective} using {name} best practices" - so a
     * candidate could pick any of the four and be right, and the one presented
     * as correct was the only option mentioning the exam at all.
     *
     * The distractors are field-neutral professional-practice anti-patterns.
     * They used to be security and operations specific ("hardcode credentials
     * and open firewall access", "disable logging and monitoring") and were
     * served unchanged to NCLEX-RN, ACCA, DELF and TOPIK items, where they were
     * meaningless - 114,198 items carried IT distractors on non-IT exams.
     *
     * @return array<int,array{key:string,text:string,is_correct:bool}>
     */
    private function options(string $name, string $objective, string $domain): array
    {
        $correct = $this->rand->pick([
            "Confirm what is required, apply the documented method, check the outcome against the requirement, and record the decision",
            "Establish the standard that applies, follow it, verify the result, and keep a record of the reasoning",
            "Gather the requirements, work the problem in a controlled way, validate the answer, and document what was done",
            "Identify the objective, choose the approach the specification supports, check it, and record the outcome",
        ]);

        $distractorPool = [
            'Proceed without confirming what is required, then adjust if a problem appears later',
            'Treat the current state as acceptable and revisit only if someone raises it',
            'Apply the change to the live working immediately to save time',
            'Delegate the work to one person with no review and no record kept',
            'Copy a method from an unverified third-party source without checking it',
            'Rely on an approach the organisation has already retired',
            'Hand the work to someone else and take no position on the outcome',
            'Skip the verification step to meet the deadline',
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
