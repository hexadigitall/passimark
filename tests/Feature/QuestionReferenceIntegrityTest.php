<?php

namespace Tests\Feature;

use App\Services\PracticeQuestionBank\UniformQuestionBankGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards question citations against the fabrication that shipped once already.
 *
 * Every one of the 205 catalogue entries was cited as "<cert name> official exam
 * guide" - a document that does not exist for any of them. The generated item
 * carried that string on all 167,133 questions, so a citation naming a real
 * examination's blueprint (ISC2 CISSP, NBME, NCSBN) pointed at nothing at all.
 *
 * These tests make provenance a contract: a citation must name a real awarding
 * body, and may only name a published document where one is mapped.
 */
class QuestionReferenceIntegrityTest extends TestCase
{
    private const CATALOG_JSON = 'docs/worldwide-205-cert-catalog.json';

    /**
     * Fabricated or duplicated rows removed from the catalogue source. The
     * reasons are the justification, kept here so re-adding one requires
     * deleting the reason too.
     */
    private const REMOVED = [
        'ARAMCO' => 'company, not an examination',
        'PMP-SA' => 'duplicate of standalone PMP',
        'KUWAIT-UE' => 'not a published examination',
        'QATAR-UE' => 'not a published examination',
        'LIFE-UK-ME' => 'duplicate of LIFE-UK',
    ];

    /** @return array<int,array<string,mixed>> */
    private function catalog(): array
    {
        return json_decode((string) file_get_contents(base_path(self::CATALOG_JSON)), true);
    }

    #[Test]
    public function every_catalogue_entry_names_a_real_awarding_body(): void
    {
        $missing = [];

        foreach ($this->catalog() as $entry) {
            if (trim((string) ($entry['regulator'] ?? '')) === '') {
                $missing[] = $entry['code'];
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Every catalogue entry must name its issuing body. Missing: '.implode(', ', $missing)
        );
    }

    #[Test]
    public function catalogue_no_longer_contains_fabricated_or_duplicate_examinations(): void
    {
        $codes = array_column($this->catalog(), 'code');

        foreach (array_keys(self::REMOVED) as $code) {
            $this->assertNotContains(
                $code,
                $codes,
                "\"{$code}\" was removed because it is a ".self::REMOVED[$code].'. Re-adding it needs a real justification.'
            );
        }
    }

    #[Test]
    public function no_catalogue_entry_is_named_after_a_company(): void
    {
        // The failure that prompted this file: an employer's name sat in the
        // exam list next to real qualifications and read as plausible.
        $suspect = [];

        foreach ($this->catalog() as $entry) {
            $name = (string) $entry['name'];

            if (preg_match('/\b(Assessment|Corporation|Inc|Ltd|PLC|GmbH|Group)\b/i', $name)
                && ! str_contains($name, 'AP Biology')) {
                $suspect[] = $entry['code'].' ('.$name.')';
            }
        }

        $this->assertSame(
            [],
            $suspect,
            'Catalogue entries must be examinations, not employers: '.implode(', ', $suspect)
        );
    }

    #[Test]
    public function the_generator_no_longer_fabricates_an_exam_guide(): void
    {
        $entry = collect($this->catalog())->firstWhere('code', 'CISSP');

        $this->assertNotNull($entry, 'CISSP must remain in the catalogue.');

        $question = UniformQuestionBankGenerator::withSeed(1234)
            ->question($entry, ['title' => 'Domain 1', 'domain' => 'Security', 'phase_type' => 'lesson'], 0);

        $this->assertStringNotContainsStringIgnoringCase(
            'official exam guide',
            $question['reference'],
            'The fabricated citation must not be reproducible from a mapped catalogue entry.'
        );
        $this->assertStringContainsString(
            'ISC2',
            $question['reference'],
            'A mapped entry must cite its real awarding body.'
        );
    }

    #[Test]
    public function an_unmapped_entry_says_so_rather_than_inventing_a_source(): void
    {
        $reference = UniformQuestionBankGenerator::referenceFor(['code' => 'ZZZ', 'name' => 'Invented Exam']);

        $this->assertStringNotContainsStringIgnoringCase('official exam guide', $reference);
        $this->assertStringContainsStringIgnoringCase(
            'not mapped',
            $reference,
            'An entry with no awarding body must be visibly unmapped, never given a plausible-looking source.'
        );
    }

    #[Test]
    public function a_blueprint_is_only_cited_when_the_body_is_known(): void
    {
        $both = UniformQuestionBankGenerator::referenceFor([
            'code' => 'X', 'name' => 'X', 'regulator' => 'Some Board', 'blueprint' => 'Published Outline',
        ]);
        $bodyOnly = UniformQuestionBankGenerator::referenceFor([
            'code' => 'Y', 'name' => 'Y', 'regulator' => 'Some Board', 'blueprint' => null,
        ]);

        $this->assertStringContainsString('Published Outline', $both);
        $this->assertStringStartsWith('Issued by Some Board', $bodyOnly);
        $this->assertStringNotContainsString('- ', $bodyOnly, 'No document title should be invented when none is mapped.');
    }
}
