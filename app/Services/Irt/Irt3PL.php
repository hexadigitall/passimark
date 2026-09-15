<?php
namespace App\Services\Irt;

/**
 * IRT 3PL engine (v4 spec section 6).
 *
 * P(theta) = c + (1 - c) / (1 + exp(-a * (theta - b)))
 *
 * Theta is recovered by Fisher-scoring Newton-Raphson on the item log-likelihood:
 *   theta_{k+1} = theta_k + score(theta_k) / I(theta_k)
 * with theta clamped to [-3, 3], at most 10 iterations, breaking on |Δ| < 0.001.
 */
class Irt3PL
{
    public const THETA_MIN = -3.0;
    public const THETA_MAX = 3.0;

    public const EPS = 1e-8;

    /**
     * 3PL probability of a correct response.
     *
     * @param array{a: float, b: float, c: float} $p
     */
    public static function p(array $p, float $theta): float
    {
        $a = (float) ($p['a'] ?? 1.0);
        $b = (float) ($p['b'] ?? 0.0);
        $c = (float) ($p['c'] ?? 0.25);
        $c = max(0.0, min(0.99, $c));
        $e = exp(-$a * ($theta - $b));
        return $c + (1 - $c) / (1 + $e);
    }

    /**
     * Fisher information for one item at theta.
     * I(theta) = a^2 * (P - c)^2 * (1 - P) / (P * (1 - c)^2)
     *
     * @param array{a: float, b: float, c: float} $p
     */
    public static function information(array $p, float $theta): float
    {
        $a = (float) ($p['a'] ?? 1.0);
        $c = max(0.0, min(0.99, (float) ($p['c'] ?? 0.25)));
        $prob = self::p($p, $theta);
        $den = $prob * (1 - $c) * (1 - $c);
        if ($den <= self::EPS) {
            return 0.0;
        }
        $info = $a * $a * ($prob - $c) * ($prob - $c) * (1 - $prob) / $den;
        return max(0.0, $info);
    }

    /**
     * First derivative of the joint log-likelihood (score) at theta.
     *
     * @param list<array{a: float, b: float, c: float, u: float}> $answers
     */
    public static function score(array $answers, float $theta): float
    {
        $total = 0.0;
        foreach ($answers as $answer) {
            $a = (float) ($answer['a'] ?? 1.0);
            $c = max(0.0, min(0.99, (float) ($answer['c'] ?? 0.25)));
            $u = (float) ($answer['u'] ?? 0);
            $prob = max(self::EPS, min(1 - self::EPS, self::p($answer, $theta)));
            $den = $prob * (1 - $prob) * (1 - $c);
            if ($den <= self::EPS) {
                continue;
            }
            $total += $a * (1 - $prob) * ($prob - $c) * ($u - $prob) / $den;
        }
        return $total;
    }

    /**
     * Total Fisher information across answered items at theta.
     *
     * @param list<array{a: float, b: float, c: float, u: float}> $answers
     */
    public static function totalInformation(array $answers, float $theta): float
    {
        $total = 0.0;
        foreach ($answers as $answer) {
            $total += self::information($answer, $theta);
        }
        return $total;
    }

    /**
     * Standard error of the theta estimate.
     *
     * @param list<array{a: float, b: float, c: float, u: float}> $answers
     */
    public static function standardError(array $answers, float $theta): ?float
    {
        $info = self::totalInformation($answers, $theta);
        if ($info <= self::EPS) {
            return null;
        }
        return 1 / sqrt($info);
    }

    /**
     * Newton-Raphson (Fisher scoring) theta estimation.
     *
     * @param list<array{a: float, b: float, c: float, u: float}> $answers
     * @return array{theta: float, iterations: int, converged: bool, se: ?float}
     */
    public static function mle(array $answers, float $theta = 0.0, int $maxIterations = 10): array
    {
        if ($answers === []) {
            return ['theta' => self::clamp($theta), 'iterations' => 0, 'converged' => true, 'se' => null];
        }

        $theta = self::clamp($theta);

        $result = self::newton($answers, $theta, $maxIterations);
        if (!$result['converged']) {
            // Warm starts pinned at the theta bound can diverge (tiny info at the
            // clamp makes Newton oscillate). Retry from the centre of the scale.
            $restart = self::newton($answers, 0.0, $maxIterations);
            if (
                $restart['converged']
                || abs(self::score($answers, $restart['theta'])) < abs(self::score($answers, $result['theta']))
            ) {
                $result = $restart;
            }
        }

        return $result;
    }

    /**
     * @param list<array{a: float, b: float, c: float, u: float}> $answers
     * @return array{theta: float, iterations: int, converged: bool, se: ?float}
     */
    private static function newton(array $answers, float $theta, int $maxIterations): array
    {
        if ($answers === []) {
            return ['theta' => self::clamp($theta), 'iterations' => 0, 'converged' => true, 'se' => null];
        }

        $theta = self::clamp($theta);
        $converged = false;
        $iteration = 0;

        for (; $iteration < $maxIterations; $iteration++) {
            $info = self::totalInformation($answers, $theta);
            $score = self::score($answers, $theta);

            if ($info > self::EPS) {
                $step = $score / $info;
            } elseif ($score > self::EPS) {
                $step = 0.1;
            } elseif ($score < -self::EPS) {
                $step = -0.1;
            } else {
                break;
            }

            $next = self::clamp($theta + $step);
            if (abs($next - $theta) < 0.001) {
                $theta = $next;
                $converged = true;
                break;
            }
            $theta = $next;
        }

        return [
            'theta' => $theta,
            'iterations' => $iteration + 1,
            'converged' => $converged,
            'se' => self::standardError($answers, $theta),
        ];
    }

    /**
     * Map a theta in [-3, 3] to a 0-100 scaled score (50 at theta = 0).
     */
    public static function scaledScore(float $theta): float
    {
        return round(50 + (self::clamp($theta) / (self::THETA_MAX - self::THETA_MIN)) * 100, 2);
    }

    public static function clamp(float $theta): float
    {
        $theta = (float) $theta;
        return max(self::THETA_MIN, min(self::THETA_MAX, $theta));
    }

    /**
     * Normalise raw item parameters into the 3PL shape {a, b, c}.
     *
     * Accepts an Eloquent model, an associative array, or an object exposing the
     * v4 canonical aliases (a_discrimination/b_difficulty/c_guessing) or the
     * legacy columns (discrimination/difficulty/guessing).
     *
     * @param array<string, mixed>|object $source
     * @return array{a: float, b: float, c: float}
     */
    public static function params(mixed $source, ?float $b = null, ?float $a = null, ?float $c = null): array
    {
        if ($source instanceof \Illuminate\Database\Eloquent\Model) {
            $arr = $source->getAttributes();
        } elseif (is_array($source)) {
            $arr = $source;
        } elseif ($source instanceof \ArrayAccess) {
            foreach ($source as $key => $value) {
                $arr[$key] = $value;
            }
        } else {
            $arr = (array) $source;
        }
        $b ??= $arr['b_difficulty'] ?? $arr['difficulty'] ?? 0.0;
        $a ??= $arr['a_discrimination'] ?? $arr['discrimination'] ?? 1.0;
        $c ??= $arr['c_guessing'] ?? $arr['guessing'] ?? 0.25;
        return [
            'a' => max(0.001, (float) $a),
            'b' => (float) $b,
            'c' => max(0.0, min(0.99, (float) $c)),
        ];
    }
}