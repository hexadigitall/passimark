<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Every CAT exam runs the v4 IRT engine - that is the platform invariant the
     * catalog seeders hold (ContentCoherenceTest asserts it). Two paths created
     * CAT exams without it: the PassimarkSeeder and, more importantly, the runtime
     * in PassimarkController::start(), which created an exam whenever a learner
     * started an attempt on a session that had no exam row yet.
     *
     * A legacy CAT exam scores the attempt with updateTheta(), which takes the
     * item's difficulty/discrimination/guessing. Authored bundles carry no
     * measured item parameters (docs/content-format.md), so those columns are null
     * and the attempt died with a TypeError on its first answer.
     *
     * Flip the flag so those attempts run the IRT engine, which normalises the
     * missing parameters through Irt3PL::params(). Timed and practice exams are
     * untouched - they never used the CAT scoring path.
     */
    public function up(): void
    {
        DB::table('passimark_exams')
            ->where('mode', 'cat')
            ->where('irt_enabled', false)
            ->update(['irt_enabled' => true]);
    }

    public function down(): void
    {
        // Data repair only; the flag is restored by the seeders.
    }
};
