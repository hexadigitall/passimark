<?php
namespace App\Services;
use App\Models\{PassimarkQuestion, PassimarkAttempt};
use App\Services\Irt\Irt3PL;

/**
 * CAT engine (v4 spec section 6).
 *
 * v4 CAT attempts (exam.irt_enabled + mode=cat) run true IRT 3PL:
 *  - theta is re-estimated by Newton-Raphson MLE over every response;
 *  - next question maximises Fisher information, preferring |b - theta| <= 0.5,
 *    tie-broken by higher discrimination;
 *  - termination honours per-exam min/max cutoffs (real CAT, e.g. NCLEX 75-145)
 *    with an early stop only when the band is open and theta is confident;
 *  - score is the theta-to-100 scaled score; passing uses session.theta_required.
 *
 * Legacy CAT (irt_enabled false) and timed/practice modes keep the original
 * nearest-difficulty / sequential + percentage behaviour.
 */
class CatEngine {
    public static function usesIrt(PassimarkAttempt $attempt): bool {
        return $attempt->mode==='cat' && (bool) optional($attempt->exam)->irt_enabled;
    }

    public static function nextQuestion(PassimarkAttempt $attempt): ?PassimarkQuestion {
        $answered = $attempt->answers()->pluck('question_id')->toArray();
        $query = PassimarkQuestion::where('session_id',$attempt->session_id)->whereNotIn('id',$answered);
        if($attempt->mode==='cat'){
            $pool = $query->get();
            if($pool->isEmpty()) return null;
            if(!self::usesIrt($attempt)){
                return $pool->sortBy(fn ($q) => abs((float)($q->difficulty ?? $q->b_difficulty ?? 0) - $attempt->theta))->first();
            }
            $theta = $attempt->theta;
            return $pool->sortByDesc(function ($q) use ($theta) {
                $p = Irt3PL::params($q);
                $preferred = abs($p['b'] - $theta) <= 0.5 ? 1 : 0;
                $info = Irt3PL::information($p, $theta);
                return ($preferred * 1.0e12) + $info * 1.0e4 + $p['a'];
            })->first();
        }
        return $query->orderBy('id')->first();
    }

    public static function shouldTerminate(PassimarkAttempt $attempt): bool {
        $count = $attempt->answers()->count();
        if($attempt->mode==='cat'){
            if(!self::usesIrt($attempt)){
                return $count >= 150 || ($count>=75 && abs($attempt->theta) > 2.5);
            }
            $exam = $attempt->exam;
            $maxQ = (int) ($exam->max_questions ?? $exam->question_count ?? 150);
            if($count >= $maxQ) return true;
            $minQ = (int) ($exam->min_questions ?? $maxQ);
            if($count >= $minQ && $minQ < $maxQ){
                $se = Irt3PL::standardError(self::answeredParams($attempt), $attempt->theta);
                $passTheta = $attempt->session?->theta_required ?? 0.0;
                if($se !== null && $se < 0.5 && abs($attempt->theta - $passTheta) > 1.5 * $se){
                    return true;
                }
            }
            return false;
        }
        return $count >= $attempt->exam->question_count;
    }

    public static function calculateScore(PassimarkAttempt $attempt): float {
        if($attempt->mode==='cat' && self::usesIrt($attempt)){
            return Irt3PL::scaledScore($attempt->theta);
        }
        $total = $attempt->answers()->count();
        if(!$total) return 0;
        $correct = $attempt->answers()->where('is_correct',true)->count();
        return round($correct/$total*100,2);
    }

    /**
     * IRT parameter vector {a,b,c,u} for every response on the attempt.
     *
     * @return list<array{a: float, b: float, c: float, u: float}>
     */
    public static function answeredParams(PassimarkAttempt $attempt): array {
        return $attempt->answers()->with('question')->get()
            ->map(fn ($answer) => [
                'a' => Irt3PL::params($answer->question)['a'],
                'b' => Irt3PL::params($answer->question)['b'],
                'c' => Irt3PL::params($answer->question)['c'],
                'u' => $answer->is_correct ? 1.0 : 0.0,
            ])
            ->values()->all();
    }
}