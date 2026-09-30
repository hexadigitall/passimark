<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow an item's psychometric parameters to be genuinely unknown.
     *
     * These three columns were NOT NULL, so the generator had to invent a value
     * for every item: difficulty and discrimination were seeded PRNG draws and
     * guessing was a hardcoded 0.25. That is not a formatting constraint, it is
     * the reason a fake number had to exist.
     *
     * It mattered because the numbers were consumed as if they were real.
     * Irt3PL::params() reads a/b/c straight off the question row, the resulting
     * theta is stored on progress as pass_probability, and CertificateIssuer
     * decides whether to issue the credential from it. AdminAnalytics additionally
     * counted "low discrimination" items as discrimination < 0.7, which the
     * generator could never emit, so that quality report read a clean zero no
     * matter what the bank contained.
     *
     * Null now propagates to Irt3PL's documented defaults, which are visibly
     * defaults rather than invented data.
     */
    public function up(): void
    {
        Schema::table('passimark_questions', function (Blueprint $table) {
            $table->float('difficulty')->nullable()->change();
            $table->float('discrimination')->nullable()->change();
            $table->float('guessing')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('passimark_questions', function (Blueprint $table) {
            $table->float('difficulty')->nullable(false)->change();
            $table->float('discrimination')->nullable(false)->change();
            $table->float('guessing')->nullable(false)->change();
        });
    }
};
