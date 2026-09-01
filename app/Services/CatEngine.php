<?php
namespace App\Services;
use App\Models\{PassimarkQuestion, PassimarkAttempt};

class CatEngine {
    public static function nextQuestion(PassimarkAttempt $attempt): ?PassimarkQuestion {
        $answered = $attempt->answers()->pluck('question_id')->toArray();
        $query = PassimarkQuestion::where('session_id',$attempt->session_id)->whereNotIn('id',$answered);
        if($attempt->mode==='cat'){
            return $query->orderByRaw('ABS(difficulty - ?) ASC', [$attempt->theta])->first();
        }
        return $query->inRandomOrder()->first();
    }
    public static function shouldTerminate(PassimarkAttempt $attempt): bool {
        $count = $attempt->answers()->count();
        if($attempt->mode==='cat'){
            return $count >= 150 || ($count>=75 && abs($attempt->theta) > 2.5);
        }
        return $count >= $attempt->exam->question_count;
    }
    public static function calculateScore(PassimarkAttempt $attempt): float {
        $total = $attempt->answers()->count();
        if(!$total) return 0;
        $correct = $attempt->answers()->where('is_correct',true)->count();
        return round($correct/$total*100,2);
    }
}
