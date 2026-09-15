<?php

namespace Tests\Unit;

use App\Services\Irt\Irt3PL;
use PHPUnit\Framework\TestCase;

class Irt3PLTest extends TestCase
{
    public function test_3pl_probability_matches_known_values(): void
    {
        $p = ['a' => 1.0, 'b' => 0.0, 'c' => 0.25];

        $this->assertSame(0.625, round(Irt3PL::p($p, 0.0), 6));
        $this->assertGreaterThan(0.9, Irt3PL::p($p, 3.0));
        $this->assertGreaterThan(0.99, Irt3PL::p($p, 8.0));
        $this->assertLessThan(0.26, Irt3PL::p($p, -8.0));
        $this->assertGreaterThan(Irt3PL::p($p, -8.0), Irt3PL::p(['a' => 1.0, 'b' => 0.0, 'c' => 0.9], -8.0));
        $this->assertLessThan(0.901, Irt3PL::p(['a' => 1.0, 'b' => 0.0, 'c' => 0.9], -8.0));
    }

    public function test_fisher_information_peaks_near_the_item_difficulty(): void
    {
        $item = fn (float $theta) => Irt3PL::information(['a' => 1.0, 'b' => 0.0, 'c' => 0.0], $theta);

        $this->assertSame(0.25, round($item(0.0), 6));
        $this->assertGreaterThan($item(-2.0), $item(0.0));
        $this->assertGreaterThan($item(2.0), $item(0.0));
        $this->assertLessThan(1e-3, $item(10.0));
    }

    public function test_mle_recovers_the_generating_theta_from_synthetic_responses(): void
    {
        mt_srand(20260915);
        $trueTheta = 1.3;
        $answers = [];
        for ($i = 0; $i < 40; $i++) {
            $item = [
                'a' => 0.5 + mt_rand() / mt_getrandmax() * 1.5,
                'b' => -1.5 + mt_rand() / mt_getrandmax() * 3.0,
                'c' => 0.25,
            ];
            $prob = Irt3PL::p($item, $trueTheta);
            $answers[] = [
                'a' => $item['a'],
                'b' => $item['b'],
                'c' => $item['c'],
                'u' => (mt_rand() / mt_getrandmax() < $prob) ? 1.0 : 0.0,
            ];
        }

        $estimate = Irt3PL::mle($answers, 0.0);

        $this->assertTrue($estimate['converged']);
        $this->assertBetween($trueTheta - 0.25, $estimate['theta'], $trueTheta + 0.25);
        $this->assertNotNull($estimate['se']);
        $this->assertLessThan(1.0, $estimate['se']);
    }

    public function test_all_correct_clamps_to_upper_bound_and_all_wrong_to_lower_bound(): void
    {
        $correct = [['a' => 1.0, 'b' => 0.0, 'c' => 0.25, 'u' => 1.0]];
        $wrong = [['a' => 1.0, 'b' => 0.0, 'c' => 0.25, 'u' => 0.0]];

        $this->assertSame(3.0, Irt3PL::mle($correct)['theta']);
        $this->assertSame(-3.0, Irt3PL::mle($wrong)['theta']);
    }

    public function test_score_is_monotone_in_theta_and_changes_sign_with_item_meanings(): void
    {
        $answers = [['a' => 1.0, 'b' => 0.0, 'c' => 0.25, 'u' => 1.0]];

        $this->assertGreaterThan(0.0, Irt3PL::score($answers, -2.0));
        $this->assertLessThan(0.0, Irt3PL::score([[...$answers[0], 'u' => 0.0]], 2.0));
    }

    public function test_scaled_score_maps_theta_to_zero_hundred_scale(): void
    {
        $this->assertSame(50.0, Irt3PL::scaledScore(0.0));
        $this->assertSame(100.0, Irt3PL::scaledScore(3.0));
        $this->assertSame(0.0, Irt3PL::scaledScore(-3.0));
        $this->assertSame(80.67, Irt3PL::scaledScore(1.84));
    }

    public function test_clamp_respects_irt_bounds(): void
    {
        $this->assertSame(-3.0, Irt3PL::clamp(-11.5));
        $this->assertSame(0.7, Irt3PL::clamp(0.7));
        $this->assertSame(3.0, Irt3PL::clamp(9.9));
    }

    private function assertBetween(float $lo, float $value, float $hi): void
    {
        $this->assertGreaterThanOrEqual($lo, $value);
        $this->assertLessThanOrEqual($hi, $value);
    }
}