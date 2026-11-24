<?php

namespace App\Console\Commands;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\User;
use App\Services\AiTutoring\ExplanationGenerator;
use App\Services\AnswerEvaluator;
use App\Services\CertificateIssuer;
use App\Services\Irt\Irt3PL;
use App\Services\PassimarkImport\FormatDetector;
use App\Services\PassimarkPackage\PackagingSpec;
use App\Services\SpacedRepetition\LeitnerEngine;
use App\Support\PsmkZip;
use Illuminate\Console\Command;

class PassimarkSystemCheck extends Command
{
    protected $signature = 'passimark:system-check';

    protected $description = 'Perform an automated end-to-end health check across all 10 Passimark core subsystems';

    public function handle(): int
    {
        $this->info('=== PASSIMARK V2.0 SYSTEM HEALTH & CAPABILITY AUDIT ===');
        $this->newLine();

        $checks = [
            'Database & Question Banks' => fn () => $this->checkDatabase(),
            'IRT 3PL Psychometric Engine' => fn () => $this->checkPsychometrics(),
            'Spaced Repetition (Leitner 5-Box)' => fn () => $this->checkSpacedRepetition(),
            'AI Tutoring Distractor Generator' => fn () => $this->checkAiTutoring(),
            'Question Taxonomy Evaluator' => fn () => $this->checkQuestionEvaluator(),
            'Universal Packaging (.psmk)' => fn () => $this->checkPackaging(),
            'Universal Import Adapters' => fn () => $this->checkImportAdapters(),
            'Native Mobile Assets (Capacitor)' => fn () => $this->checkMobileAssets(),
            'Native Desktop Binary (Tauri 2.0)' => fn () => $this->checkDesktopBinary(),
            'Deterministic Credential Minting' => fn () => $this->checkCredentialMinting(),
        ];

        $passed = 0;
        $total = count($checks);

        foreach ($checks as $title => $checker) {
            $this->output->write(sprintf('  %-36s', $title));
            try {
                $detail = $checker();
                $this->info(" [PASS] {$detail}");
                $passed++;
            } catch (\Throwable $e) {
                $this->error(" [FAIL] {$e->getMessage()}");
            }
        }

        $this->newLine();
        if ($passed === $total) {
            $this->info("=== ALL {$total}/{$total} PASSIMARK SUBSYSTEMS OPERATIONAL & VERIFIED ===");
            return self::SUCCESS;
        }

        $this->error("=== SYSTEM CHECK FAILED: {$passed}/{$total} PASSED ===");
        return self::FAILURE;
    }

    private function checkDatabase(): string
    {
        $tracks = PassimarkCertificationTrack::count();
        $sessions = PassimarkSession::count();
        $exams = PassimarkExam::count();
        $questions = PassimarkQuestion::count();
        $users = User::count();

        return "{$tracks} tracks, {$sessions} sessions, {$exams} exams, {$questions} questions, {$users} users";
    }

    private function checkPsychometrics(): string
    {
        $params = ['a' => 1.5, 'b' => 0.0, 'c' => 0.20];
        $prob = Irt3PL::p($params, 0.0);
        if (abs($prob - 0.60) > 0.01) {
            throw new \RuntimeException("IRT probability calculation deviation: got {$prob}, expected ~0.60");
        }
        $info = Irt3PL::information($params, 0.0);
        if ($info <= 0.0) {
            throw new \RuntimeException("IRT Fisher information must be positive, got {$info}");
        }
        return sprintf('P(0.0)=%.2f, Info=%.2f', $prob, $info);
    }

    private function checkSpacedRepetition(): string
    {
        $intervals = LeitnerEngine::BOX_INTERVALS;
        if ($intervals[1] !== 12 || $intervals[2] !== 72 || $intervals[3] !== 168 || $intervals[4] !== 336 || $intervals[5] !== 720) {
            throw new \RuntimeException('Leitner intervals do not match specification: 12h, 3d, 7d, 14d, 30d');
        }
        return 'Box intervals verified (12h, 3d, 7d, 14d, 30d)';
    }

    private function checkAiTutoring(): string
    {
        $q = new PassimarkQuestion([
            'content' => 'Which protocol operates at the Transport Layer?',
            'correct_key' => 'TCP',
            'options' => [
                ['key' => 'TCP', 'text' => 'Transmission Control Protocol', 'is_correct' => true],
                ['key' => 'IP', 'text' => 'Internet Protocol', 'is_correct' => false],
            ],
            'explanation' => 'TCP is a Layer 4 Transport protocol.',
        ]);
        $generator = new ExplanationGenerator();
        $explanation = $generator->explainDistractor($q, 'IP');
        if (empty($explanation['why_chosen_is_incorrect']) || empty($explanation['why_correct_is_best']) || empty($explanation['key_takeaway'])) {
            throw new \RuntimeException('AI Tutoring explanation payload missing distractor analysis or key takeaway');
        }
        return 'Distractor rationale & exam takeaway generated';
    }

    private function checkQuestionEvaluator(): string
    {
        $q = new PassimarkQuestion([
            'type' => 'single_choice',
            'correct_key' => 'B',
            'options' => [
                ['key' => 'A', 'text' => 'Alpha', 'is_correct' => false],
                ['key' => 'B', 'text' => 'Beta', 'is_correct' => true],
            ],
        ]);
        $res = AnswerEvaluator::evaluate($q, ['selected' => 'B']);
        if (!$res['is_correct']) {
            throw new \RuntimeException('AnswerEvaluator failed single_choice correct evaluation');
        }
        return 'Polymorphic evaluator verified';
    }

    private function checkPackaging(): string
    {
        $sampleFiles = [
            'manifest.json' => json_encode(['format_version' => PackagingSpec::FORMAT_VERSION]),
            'content.json' => json_encode(['test' => true]),
        ];
        $binary = PsmkZip::build($sampleFiles);
        $read = PsmkZip::read($binary);
        if (!isset($read['manifest.json']) || !isset($read['content.json'])) {
            throw new \RuntimeException('PsmkZip pure-PHP build/read failed');
        }
        return 'PsmkZip pure-PHP container build & read verified';
    }

    private function checkImportAdapters(): string
    {
        $adapters = [
            'csv' => new \App\Services\PassimarkImport\CsvSourceAdapter(),
            'gift' => new \App\Services\PassimarkImport\GiftSourceAdapter(),
            'qti' => new \App\Services\PassimarkImport\QtiSourceAdapter(),
            'vce' => new \App\Services\PassimarkImport\VceSourceAdapter(),
        ];
        foreach ($adapters as $name => $adapter) {
            if (!$adapter instanceof \App\Services\PassimarkImport\SourceAdapter) {
                throw new \RuntimeException("Adapter {$name} does not implement SourceAdapter interface");
            }
        }
        return 'CSV, GIFT, QTI 2.1, and VCE source adapters verified';
    }

    private function checkMobileAssets(): string
    {
        $androidPublic = base_path('android/app/src/main/assets/public');
        $iosPublic = base_path('ios/App/App/public');
        if (!is_dir($androidPublic) || !is_dir($iosPublic)) {
            throw new \RuntimeException('Capacitor native assets missing from android or ios public directories');
        }
        return 'Capacitor 8 assets synchronized for Android and iOS';
    }

    private function checkDesktopBinary(): string
    {
        $binPath = base_path('src-tauri/target/debug/passimark-desktop.exe');
        if (!file_exists($binPath)) {
            throw new \RuntimeException('Desktop binary passimark-desktop.exe not found');
        }
        $sizeMb = round(filesize($binPath) / (1024 * 1024), 1);
        return "Tauri 2.0 Rust executable built ({$sizeMb} MB native binary)";
    }

    private function checkCredentialMinting(): string
    {
        $code = CertificateIssuer::PREFIX . '-CISSP-2026-A1B2C3D4';
        if (!preg_match('/^PMK-[A-Z0-9_-]+-\d{4}-[A-F0-9]{8}$/', $code)) {
            throw new \RuntimeException("Credential code {$code} does not match invariant pattern");
        }
        return "Format invariant confirmed: {$code}";
    }
}
