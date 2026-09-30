<?php

namespace App\Console\Commands;

use App\Models\PassimarkAttempt;
use App\Models\PassimarkAttemptAnswer;
use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\User;
use App\Services\Curriculum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Put a learner back into a genuine first-run state for manual QA.
 *
 * Deliberately does NOT offer a "wipe everything" mode. `migrate:fresh --seed`
 * is the obvious way to reset a Laravel app and it is a trap here: SEED_CATALOG
 * is unset in .env, so the default seed path builds the CISSP bundle rather
 * than the 205-cert uniform ladder, and the 205 tracks / 2,870 sessions /
 * 167,133 questions that are the product would be replaced by one track. The
 * catalog is seeded content, not user data, so it is preserved here and its
 * counts are reported so a mistake is visible.
 */
class ResetDemoLearner extends Command
{
    protected $signature = 'passimark:demo-reset
        {--email=* : Learner emails to reset. Defaults to every non-staff user.}
        {--purge : Delete the learner entirely, so they can register from scratch.}
        {--staff : Also ensure an admin account exists, for the admin and report screens.}
        {--staff-email=staff@passimark.com : Email for the --staff account.}
        {--staff-password=password : Password for the --staff account.}
        {--force : Skip the confirmation prompt.}';

    protected $description = 'Reset a learner to a true first-run state (funnel, no focus, no progress) without touching the catalog.';

    /** Roles that are staff rather than learners, so a blanket reset skips them. */
    private const STAFF_ROLES = ['admin', 'instructor'];

    public function handle(): int
    {
        $catalog = [
            'tracks' => PassimarkCertificationTrack::count(),
            'sessions' => PassimarkSession::count(),
            'questions' => PassimarkQuestion::count(),
        ];

        $this->newLine();
        $this->line('  <fg=green>Preserved catalog (seeded content, never touched):</>');
        foreach ($catalog as $label => $count) {
            $this->line(sprintf('    %-10s %s', $label, number_format($count)));
        }
        $this->newLine();

        $learners = $this->resolveLearners();

        if ($learners->isEmpty()) {
            $this->components->error('No matching learner accounts. Nothing to reset.');
            $this->newLine();

            if ($this->option('staff')) {
                $this->ensureStaff();
            }

            return self::SUCCESS;
        }

        $this->line('  <fg=yellow>Will reset:</>');
        foreach ($learners as $learner) {
            $this->line(sprintf(
                '    %s <fg=gray>(%s, role=%s)</>',
                $learner->email,
                $learner->preferences ? 'has preferences' : 'no preferences',
                $learner->role ?? 'none'
            ));
        }
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Clear their progress, attempts and preferences?', false)) {
            $this->components->warn('Aborted. Nothing was changed.');

            return self::FAILURE;
        }

        $totals = ['progress' => 0, 'attempts' => 0, 'answers' => 0, 'purged' => 0];

        foreach ($learners as $learner) {
            DB::transaction(function () use ($learner, &$totals) {
                // Certificates are not a table: they are the certified_at /
                // credential_id columns on progress, so clearing progress is what
                // actually withdraws a credential.
                $attemptIds = PassimarkAttempt::where('user_id', $learner->id)->pluck('id');
                $totals['answers'] += PassimarkAttemptAnswer::whereIn('attempt_id', $attemptIds)->delete();
                $totals['attempts'] += PassimarkAttempt::where('user_id', $learner->id)->delete();
                $totals['progress'] += PassimarkProgress::where('user_id', $learner->id)->delete();

                if ($this->option('purge')) {
                    $learner->delete();
                    $totals['purged']++;
                } else {
                    // The funnel gate reads preferences.funnel_completed, and the
                    // dashboard reads preferences.focus. Clearing both is what
                    // puts a learner back on the first-run ladder.
                    $learner->preferences = null;
                    $learner->save();

                    // Deleting progress also deletes the ladder's entry point: the
                    // ladder only starts a session that has an open progress row.
                    // Re-open the first assessable step of each active track so a
                    // reset learner lands on a usable first run, not an empty one.
                    Curriculum::enrollFirstSteps($learner);
                }
            });

            $this->components->twoColumnDetail($learner->email, $this->option('purge') ? '<fg=yellow>purged</>' : '<fg=green>reset</>');
        }

        $this->newLine();
        $this->line('  <fg=green>Done:</>');
        $this->line(sprintf('    %-10s %s', 'progress', number_format($totals['progress'])));
        $this->line(sprintf('    %-10s %s', 'attempts', number_format($totals['attempts'])));
        $this->line(sprintf('    %-10s %s', 'answers', number_format($totals['answers'])));
        if ($totals['purged'] > 0) {
            $this->line(sprintf('    %-10s %s', 'accounts', number_format($totals['purged']).' deleted'));
        }

        if ($this->option('staff')) {
            $this->newLine();
            $this->ensureStaff();
        }

        $this->newLine();
        $this->line('  <fg=gray>Next: sign in as the learner and you will land on /passimark/lock.</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function resolveLearners()
    {
        $emails = (array) $this->option('email');

        $query = User::query();

        if ($emails !== []) {
            $query->whereIn('email', $emails);
        } else {
            // A blanket reset must not log the staff out of the reports they are
            // about to go and check.
            $query->whereNotIn('role', self::STAFF_ROLES);
        }

        return $query->get();
    }

    private function ensureStaff(): void
    {
        $email = (string) $this->option('staff-email');
        $password = (string) $this->option('staff-password');

        $staff = User::where('email', $email)->first();

        if ($staff) {
            // Admin and report routes are gated on role:instructor,admin, so a
            // learner account simply cannot open them.
            if (! in_array($staff->role, self::STAFF_ROLES, true)) {
                $staff->role = 'admin';
                $staff->save();
            }

            $this->components->twoColumnDetail($email, '<fg=gray>exists, role='.$staff->role.'</>');

            return;
        }

        User::create([
            'name' => 'Passimark Staff',
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'email_verified_at' => now(),
            'preferences' => ['funnel_completed' => true],
        ]);

        $this->components->twoColumnDetail($email, '<fg=green>created (role=admin)</>');
        $this->line('    <fg=yellow>Sign in with:</> '.$email.' / '.$password);
        $this->line('    <fg=gray>Then open /admin/passimark and the six /admin/reports/* screens.</>');
    }
}
