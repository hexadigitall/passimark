<?php

namespace Tests\Unit;

use App\Models\PassimarkQuestion;
use App\Services\AnswerEvaluator;
use PHPUnit\Framework\TestCase;

class AnswerEvaluatorTest extends TestCase
{
    public function test_single_choice_evaluation(): void
    {
        $question = new PassimarkQuestion([
            'type' => 'single_choice',
            'correct_key' => 'B',
            'options' => [
                ['key' => 'A', 'text' => 'Option A', 'is_correct' => false],
                ['key' => 'B', 'text' => 'Option B', 'is_correct' => true],
                ['key' => 'C', 'text' => 'Option C', 'is_correct' => false],
            ],
        ]);

        $correct = AnswerEvaluator::evaluate($question, ['selected' => 'B']);
        $this->assertTrue($correct['is_correct']);
        $this->assertEquals(1.0, $correct['score']);

        $incorrect = AnswerEvaluator::evaluate($question, ['selected' => 'A']);
        $this->assertFalse($incorrect['is_correct']);
        $this->assertEquals(0.0, $incorrect['score']);
    }

    public function test_multiple_choice_exact_match(): void
    {
        $question = new PassimarkQuestion([
            'type' => 'multiple_choice',
            'options' => [
                ['key' => 'A', 'text' => 'Option A', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Option B', 'is_correct' => false],
                ['key' => 'C', 'text' => 'Option C', 'is_correct' => true],
                ['key' => 'D', 'text' => 'Option D', 'is_correct' => false],
            ],
        ]);

        $correct = AnswerEvaluator::evaluate($question, ['selections' => ['A', 'C']]);
        $this->assertTrue($correct['is_correct']);
        $this->assertEquals(1.0, $correct['score']);

        $partial = AnswerEvaluator::evaluate($question, ['selections' => ['A']]);
        $this->assertFalse($partial['is_correct']);
        $this->assertEquals(0.0, $partial['score']);

        $wrong = AnswerEvaluator::evaluate($question, ['selections' => ['A', 'B', 'C']]);
        $this->assertFalse($wrong['is_correct']);
    }

    public function test_drag_and_drop_matching(): void
    {
        $question = new PassimarkQuestion([
            'type' => 'drag_drop',
            'interactive_payload' => [
                'sources' => [
                    ['id' => 'src-tcp', 'label' => 'TCP'],
                    ['id' => 'src-ip', 'label' => 'IP'],
                    ['id' => 'src-eth', 'label' => 'Ethernet'],
                ],
                'targets' => [
                    ['id' => 'tgt-l4', 'slot_label' => 'Transport', 'correct_source_id' => 'src-tcp'],
                    ['id' => 'tgt-l3', 'slot_label' => 'Network', 'correct_source_id' => 'src-ip'],
                    ['id' => 'tgt-l2', 'slot_label' => 'Data Link', 'correct_source_id' => 'src-eth'],
                ],
            ],
        ]);

        // Perfect match
        $allCorrect = AnswerEvaluator::evaluate($question, [
            'interaction_response' => [
                'tgt-l4' => 'src-tcp',
                'tgt-l3' => 'src-ip',
                'tgt-l2' => 'src-eth',
            ],
        ]);
        $this->assertTrue($allCorrect['is_correct']);
        $this->assertEquals(1.0, $allCorrect['score']);

        // Partial match (2 of 3)
        $twoCorrect = AnswerEvaluator::evaluate($question, [
            'interaction_response' => [
                'tgt-l4' => 'src-tcp',
                'tgt-l3' => 'src-ip',
                'tgt-l2' => 'src-tcp', // wrong
            ],
        ]);
        $this->assertFalse($twoCorrect['is_correct']);
        $this->assertEquals(0.6667, round($twoCorrect['score'], 4));

        // Complete mismatch
        $zeroCorrect = AnswerEvaluator::evaluate($question, [
            'interaction_response' => [
                'tgt-l4' => 'src-eth',
                'tgt-l3' => 'src-tcp',
                'tgt-l2' => 'src-ip',
            ],
        ]);
        $this->assertFalse($zeroCorrect['is_correct']);
        $this->assertEquals(0.0, $zeroCorrect['score']);
    }

    public function test_hotspot_coordinate_evaluation_circular(): void
    {
        $question = new PassimarkQuestion([
            'type' => 'hotspot',
            'interactive_payload' => [
                'target_shape' => 'circle',
                'target_bounds' => [
                    'center_x' => 100.0,
                    'center_y' => 150.0,
                    'radius' => 20.0,
                ],
            ],
        ]);

        // Exact center hit
        $centerHit = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['x' => 100.0, 'y' => 150.0],
        ]);
        $this->assertTrue($centerHit['is_correct']);
        $this->assertEquals(1.0, $centerHit['score']);

        // Edge hit within radius 20 (distance = 15)
        $edgeHit = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['x' => 109.0, 'y' => 162.0],
        ]);
        $this->assertTrue($edgeHit['is_correct']);

        // Outside radius (distance = 25)
        $miss = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['x' => 125.0, 'y' => 150.0],
        ]);
        $this->assertFalse($miss['is_correct']);
        $this->assertEquals(0.0, $miss['score']);
    }

    public function test_hotspot_coordinate_evaluation_rectangular(): void
    {
        $question = new PassimarkQuestion([
            'type' => 'hotspot',
            'interactive_payload' => [
                'target_shape' => 'rect',
                'target_bounds' => [
                    'x_min' => 50,
                    'y_min' => 50,
                    'x_max' => 150,
                    'y_max' => 100,
                ],
            ],
        ]);

        $inside = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['x' => 75, 'y' => 80],
        ]);
        $this->assertTrue($inside['is_correct']);

        $outside = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['x' => 45, 'y' => 80],
        ]);
        $this->assertFalse($outside['is_correct']);
    }

    public function test_candidate_payload_sanitization_prevents_leakage(): void
    {
        $dragDrop = new PassimarkQuestion([
            'type' => 'drag_drop',
            'interactive_payload' => [
                'sources' => [['id' => 's1', 'label' => 'Source 1']],
                'targets' => [['id' => 't1', 'slot_label' => 'Target 1', 'correct_source_id' => 's1']],
            ],
        ]);

        $sanitized = AnswerEvaluator::sanitizeForCandidate($dragDrop, false);
        $this->assertArrayNotHasKey('correct_source_id', $sanitized['targets'][0]);

        $revealed = AnswerEvaluator::sanitizeForCandidate($dragDrop, true);
        $this->assertArrayHasKey('correct_source_id', $revealed['targets'][0]);

        $hotspot = new PassimarkQuestion([
            'type' => 'hotspot',
            'interactive_payload' => [
                'image_src' => 'diagram.png',
                'target_bounds' => ['center_x' => 100, 'center_y' => 200, 'radius' => 15],
            ],
        ]);

        $sanitizedHotspot = AnswerEvaluator::sanitizeForCandidate($hotspot, false);
        $this->assertArrayNotHasKey('target_bounds', $sanitizedHotspot);
        $this->assertEquals('diagram.png', $sanitizedHotspot['image_src']);
    }

    public function test_build_list_evaluation(): void
    {
        $question = new PassimarkQuestion([
            'type' => 'build_list',
            'interactive_payload' => [
                'correct_sequence' => ['step-1', 'step-2', 'step-3'],
                'scoring_strategy' => 'kendall_tau_partial',
            ],
        ]);

        $correct = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['step-1', 'step-2', 'step-3'],
        ]);
        $this->assertTrue($correct['is_correct']);
        $this->assertEquals(1.0, $correct['score']);

        $partial = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['step-1', 'step-3', 'step-2'],
        ]);
        $this->assertFalse($partial['is_correct']);
        $this->assertEquals(0.3333, $partial['score']); // 1 of 3 matches correct slot

        $sanitized = AnswerEvaluator::sanitizeForCandidate($question, false);
        $this->assertArrayNotHasKey('correct_sequence', $sanitized);
    }

    public function test_cloze_evaluation(): void
    {
        $question = new PassimarkQuestion([
            'type' => 'cloze',
            'interactive_payload' => [
                'template' => 'Use {{b1}} with {{b2}}.',
                'blanks' => [
                    'b1' => ['options' => ['TCP', 'UDP'], 'correct_answer' => 'TCP'],
                    'b2' => ['options' => ['TLS', 'Plain'], 'correct_answer' => 'TLS'],
                ],
            ],
        ]);

        $correct = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['b1' => 'TCP', 'b2' => 'TLS'],
        ]);
        $this->assertTrue($correct['is_correct']);
        $this->assertEquals(1.0, $correct['score']);

        $partial = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['b1' => 'TCP', 'b2' => 'Plain'],
        ]);
        $this->assertFalse($partial['is_correct']);
        $this->assertEquals(0.5, $partial['score']);

        $sanitized = AnswerEvaluator::sanitizeForCandidate($question, false);
        $this->assertArrayNotHasKey('correct_answer', $sanitized['blanks']['b1']);
        $this->assertArrayNotHasKey('correct_answer', $sanitized['blanks']['b2']);
    }

    public function test_create_tree_evaluation(): void
    {
        $question = new PassimarkQuestion([
            'type' => 'create_tree',
            'interactive_payload' => [
                'root_label' => 'Root',
                'correct_edges' => [
                    ['parent' => 'root', 'child' => 'n1'],
                    ['parent' => 'n1', 'child' => 'n2'],
                ],
            ],
        ]);

        // List format submission
        $correct = AnswerEvaluator::evaluate($question, [
            'interaction_response' => [
                ['parent' => 'root', 'child' => 'n1'],
                ['parent' => 'n1', 'child' => 'n2'],
            ],
        ]);
        $this->assertTrue($correct['is_correct']);
        $this->assertEquals(1.0, $correct['score']);

        // Map format submission
        $correctMap = AnswerEvaluator::evaluate($question, [
            'interaction_response' => [
                'n1' => 'root',
                'n2' => 'n1',
            ],
        ]);
        $this->assertTrue($correctMap['is_correct']);
        $this->assertEquals(1.0, $correctMap['score']);

        // Missing edge
        $partial = AnswerEvaluator::evaluate($question, [
            'interaction_response' => [
                ['parent' => 'root', 'child' => 'n1'],
            ],
        ]);
        $this->assertFalse($partial['is_correct']);
        $this->assertEquals(0.5, $partial['score']);

        $sanitized = AnswerEvaluator::sanitizeForCandidate($question, false);
        $this->assertArrayNotHasKey('correct_edges', $sanitized);
    }

    public function test_fill_blank_evaluation(): void
    {
        $question = new PassimarkQuestion([
            'type' => 'fill_blank',
            'interactive_payload' => [
                'accepted_answers' => ['192.168.1.1', '192.168.1.1/24'],
                'case_sensitive' => false,
                'regex_pattern' => '/^192\\.168\\.1\\.1(\\/24)?$/',
            ],
        ]);

        $correct = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['text' => '192.168.1.1'],
        ]);
        $this->assertTrue($correct['is_correct']);
        $this->assertEquals(1.0, $correct['score']);

        $correctRegex = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['text' => '192.168.1.1/24'],
        ]);
        $this->assertTrue($correctRegex['is_correct']);

        $wrong = AnswerEvaluator::evaluate($question, [
            'interaction_response' => ['text' => '10.0.0.1'],
        ]);
        $this->assertFalse($wrong['is_correct']);

        $sanitized = AnswerEvaluator::sanitizeForCandidate($question, false);
        $this->assertArrayNotHasKey('accepted_answers', $sanitized);
        $this->assertArrayNotHasKey('regex_pattern', $sanitized);
    }
}
