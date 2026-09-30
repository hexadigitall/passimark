<?php

namespace Tests\Feature;

use App\Services\PracticeQuestionBank\UniformQuestionBankGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the generated item bank against the defects the audit found.
 *
 * A full pass over the live bank measured, on 162,938 items:
 *   94,606  objective named no subject at all
 *   22,201  ungrammatical stem from splicing a bare verb ("how to handle sustain accuracy")
 *   54,310  stem printed the answer ("... {$name} guidance" + "using {$name} best practices")
 *  114,198  IT/security distractors served to a non-IT examination
 *   54,261  invented company asserted as a real organisation
 *    40,902 copies of one wording
 *
 * The bank cannot be made into real examination content: the 205 catalogue
 * carries no per-exam objectives and every session title is one of four
 * placeholders. What these tests protect is that the items are at least
 * internally coherent, honestly labelled, and do not lie about their sources or
 * their measured properties.
 */
class SyntheticItemIntegrityTest extends TestCase
{
    private const CATALOG_JSON = 'docs/worldwide-205-cert-catalog.json';

    /** Real examinations from different fields, used to check field-neutrality. */
    private const SAMPLE_CODES = [
        'NCLEX-RN',    // nursing, USA
        'DELF-B2',     // French language, EU
        'ACCA-FM',     // accounting, UK
        'JLPT-N5',     // Japanese language, APAC
        'PMP',         // project management, USA
        'JAMB-SCI',    // science, Africa
    ];

    /** Phrases that were spliced in and produced broken English. */
    private const BAD_SPLICES = [
        'how to handle sustain',
        'how to handle demonstrate',
        'how to handle perform',
        'how to handle complete',
        'under the work through',
        'under the Foundations material',
    ];

    /** Companies the generator invented and presented as real organisations. */
    private const INVENTED_COMPANIES = [
        'PaySwift Ltd', 'NaijaMart', 'Lagos Logistics Co.', 'AeroTech NG', 'FinEdge Africa',
        'Zaria Health Systems', 'Kano AgriTech', 'Harbourline Bank', 'Savannah Telecom',
    ];

    /** Security/IT vocabulary that must not appear in a non-IT item. */
    private const IT_VOCABULARY = [
        'firewall', 'credentials', 'hardcode', 'least privilege', 'production deploy',
        'third-party configuration', 'logging and monitoring',
    ];

    /** @return array<int,array<string,mixed>> */
    private function catalog(): array
    {
        return json_decode((string) file_get_contents(base_path(self::CATALOG_JSON)), true);
    }

    /** @return array<int,array{0:string,1:array<string,mixed>}> */
    public static function sampleProvider(): array
    {
        $cases = [];
        foreach (['NCLEX-RN', 'DELF-B2', 'ACCA-FM', 'JLPT-N5', 'PMP', 'JAMB-SCI'] as $code) {
            $cases[$code] = [$code];
        }

        return $cases;
    }

    private function entry(string $code): array
    {
        foreach ($this->catalog() as $entry) {
            if (strcasecmp((string) $entry['code'], $code) === 0) {
                return $entry;
            }
        }

        $this->fail("Catalogue entry {$code} not found.");
    }

    /** Every phase type, so the objective/area pair for each is exercised. */
    private const PHASES = ['lesson', 'cert', 'phase', 'domain', 'mock', 'final'];

    #[Test]
    #[DataProvider('sampleProvider')]
    public function stems_are_grammatical_and_free_of_spliced_verbs(string $code): void
    {
        $entry = $this->entry($code);

        foreach (self::PHASES as $phase) {
            for ($i = 0; $i < 6; $i++) {
                $context = ['title' => 'Lesson 1: Foundations', 'domain' => $code, 'phase_type' => $phase];
                $item = UniformQuestionBankGenerator::withSeed(crc32($code.$phase.$i))->question($entry, $context, $i);
                $content = (string) $item['content'];

                foreach (self::BAD_SPLICES as $bad) {
                    $this->assertStringNotContainsStringIgnoringCase(
                        $bad,
                        $content,
                        "[{$code}/{$phase}] broken splice survived: {$content}"
                    );
                }

                // A sentence must not start mid-phrase or trail a bare verb.
                $this->assertDoesNotMatchRegularExpression(
                    '/\b(to|of|for|with|and|under|within)\s+[a-z]+\s*(material|lesson)\b/i',
                    $content,
                    "[{$code}/{$phase}] awkward preposition chain: {$content}"
                );
            }
        }
    }

    #[Test]
    #[DataProvider('sampleProvider')]
    public function exactly_one_option_is_correct(string $code): void
    {
        $entry = $this->entry($code);

        for ($i = 0; $i < 25; $i++) {
            $context = ['title' => 'Lesson 2: Core Concepts', 'domain' => $code, 'phase_type' => 'lesson'];
            $item = UniformQuestionBankGenerator::withSeed(crc32('k'.$code.$i))->question($entry, $context, $i);
            $options = $item['options'];

            $correct = array_filter($options, fn ($o) => ! empty($o['is_correct']));

            $this->assertCount(1, $correct, "[{$code}] an item must have exactly one correct option");
            $this->assertCount(4, $options, "[{$code}] an item must have four options");

            $texts = array_map(fn ($o) => $o['text'], $options);
            $this->assertSame(
                count($texts),
                count(array_unique($texts)),
                "[{$code}] options must be distinct"
            );
        }
    }

    #[Test]
    #[DataProvider('sampleProvider')]
    public function the_stem_does_not_print_the_answer(string $code): void
    {
        $entry = $this->entry($code);

        for ($i = 0; $i < 25; $i++) {
            $context = ['title' => 'Lesson 2: Core Concepts', 'domain' => $code, 'phase_type' => 'lesson'];
            $item = UniformQuestionBankGenerator::withSeed(crc32('g'.$code.$i))->question($entry, $context, $i);
            $content = (string) $item['content'];

            $this->assertStringNotContainsStringIgnoringCase(
                'guidance',
                $content,
                "[{$code}] the stem must not offer the cue the correct option keys on"
            );

            foreach ($item['options'] as $option) {
                $this->assertStringNotContainsStringIgnoringCase(
                    'best practice',
                    (string) $option['text'],
                    "[{$code}] options must not restate the stem's cue"
                );
            }
        }
    }

    #[Test]
    #[DataProvider('sampleProvider')]
    public function no_invented_company_is_asserted_as_real(string $code): void
    {
        $entry = $this->entry($code);

        for ($i = 0; $i < 25; $i++) {
            $context = ['title' => 'Lesson 3: Advanced Basics', 'domain' => $code, 'phase_type' => 'lesson'];
            $item = UniformQuestionBankGenerator::withSeed(crc32('c'.$code.$i))->question($entry, $context, $i);
            $haystack = $item['content'].' '.json_encode($item['options']);

            foreach (self::INVENTED_COMPANIES as $company) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $company,
                    $haystack,
                    "[{$code}] invented organisation {$company} presented as real"
                );
            }
        }
    }

    #[Test]
    #[DataProvider('sampleProvider')]
    public function non_it_items_carry_no_security_vocabulary(string $code): void
    {
        $entry = $this->entry($code);

        // Every sampled examination except PMP is outside the security/cloud field.
        $nonIt = ['NCLEX-RN', 'DELF-B2', 'ACCA-FM', 'JLPT-N5', 'JAMB-SCI'];
        if (! in_array($code, $nonIt, true)) {
            $this->assertTrue(true);

            return;
        }

        for ($i = 0; $i < 25; $i++) {
            $context = ['title' => 'Lesson 1: Foundations', 'domain' => $code, 'phase_type' => 'mock'];
            $item = UniformQuestionBankGenerator::withSeed(crc32('s'.$code.$i))->question($entry, $context, $i);
            $haystack = strtolower($item['content'].' '.json_encode($item['options']));

            foreach (self::IT_VOCABULARY as $word) {
                $this->assertStringNotContainsString(
                    $word,
                    $haystack,
                    "[{$code}] security vocabulary in a non-security examination: {$word}"
                );
            }
        }
    }

    #[Test]
    #[DataProvider('sampleProvider')]
    public function no_item_asserts_measured_psychometric_properties(string $code): void
    {
        $entry = $this->entry($code);

        for ($i = 0; $i < 10; $i++) {
            $context = ['title' => 'Lesson 1: Foundations', 'domain' => $code, 'phase_type' => 'final'];
            $item = UniformQuestionBankGenerator::withSeed(crc32('p'.$code.$i))->question($entry, $context, $i);

            // Irt3PL::params() reads these straight off the row; the resulting theta
            // becomes pass_probability, and CertificateIssuer issues on that.
            $this->assertNull($item['difficulty'], 'difficulty must be null, not an invented number');
            $this->assertNull($item['discrimination'], 'discrimination must be null, not an invented number');
            $this->assertNull($item['guessing'], 'guessing must be null, not a hardcoded constant');
        }
    }

    #[Test]
    #[DataProvider('sampleProvider')]
    public function every_item_declares_itself_a_placeholder(string $code): void
    {
        $entry = $this->entry($code);

        for ($i = 0; $i < 10; $i++) {
            $context = ['title' => 'Lesson 1: Foundations', 'domain' => $code, 'phase_type' => 'lesson'];
            $item = UniformQuestionBankGenerator::withSeed(crc32('e'.$code.$i))->question($entry, $context, $i);

            $this->assertStringContainsString('Placeholder item', (string) $item['explanation']);
            $this->assertStringContainsString('not drawn from a real examination paper', (string) $item['explanation']);
            $this->assertStringStartsWith('SYNTH', (string) $item['objective_reference']);
        }
    }
}
