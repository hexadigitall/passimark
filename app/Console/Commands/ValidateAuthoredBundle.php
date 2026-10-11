<?php

namespace App\Console\Commands;

use App\Services\AuthoredBundle;
use Illuminate\Console\Command;

/**
 * Validate an authored bundle before it reaches the database.
 *
 * Enforces the rules in docs/content-format.md and prints the projected answer
 * distribution so an author can see balance without touching anything.
 */
class ValidateAuthoredBundle extends Command
{
    protected $signature = 'passimark:validate-bundle
        {bundle : Bundle cert key, or a path relative to data/bundles (e.g. "cisa" or "cisa.json").}';

    protected $description = 'Validate an authored bundle JSON against the content-format rules.';

    public function handle(): int
    {
        $key = trim((string) $this->argument('bundle'));
        $key = preg_replace('/\.json$/i', '', $key);
        $key = preg_replace('#^.*[\\\\/]#', '', $key);

        try {
            $bundle = new AuthoredBundle($key);
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        [$errors, $warnings] = $bundle->validate();
        $data = $bundle->data();

        $this->newLine();
        $this->line('  <fg=green>Bundle:</> '.$data['title'].' <fg=gray>('.$data['issuer'].', '.$bundle->certKey().')</>');
        $this->line('  <fg=gray>Provenance:</> '.($data['content_provenance'] ?? '(missing)'));
        $this->newLine();

        if ($errors === [] && $warnings === []) {
            $this->components->success('Validation passed: no errors, no warnings.');
        } else {
            foreach ($errors as $error) {
                $this->line('  <fg=red>✖</> '.$error);
            }
            foreach ($warnings as $warning) {
                $this->line('  <fg=yellow>!</> '.$warning);
            }
        }

        $groups = $bundle->domainGroups();
        $this->newLine();
        $this->line('  <fg=cyan>Session plan (seeder makes one session per domain):</>');
        foreach ($groups as $group) {
            $this->line(sprintf('    %-52s %d items', Str($group['domain'])->limit(52), count($group['items'])));
        }
        $this->line(sprintf('    %-52s %d items', 'TOTAL', count($bundle->items())));

        $distribution = $bundle->projectedKeyDistribution();
        $this->newLine();
        $this->line('  <fg=cyan>Projected correct-key distribution after rotation:</>');
        foreach ($distribution as $keyLetter => $count) {
            $this->line(sprintf('    %s: %d', $keyLetter, $count));
        }

        $this->newLine();
        if ($errors !== []) {
            $this->line('  <fg=red>Fix the errors above, then re-run. Nothing was seeded.</>');

            return self::FAILURE;
        }

        $this->line('  <fg=green>Ready to seed:</> php artisan passimark:seed-bundle --bundle='.$key);

        return self::SUCCESS;
    }
}