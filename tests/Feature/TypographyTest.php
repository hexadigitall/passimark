<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the brand typeface.
 *
 * The app previously declared a `font-display` token in tailwind.config.js and
 * referenced it zero times, so "the brand font" was whatever the OS picked:
 * Segoe UI on Windows, San Francisco on macOS, Roboto on Android. Three users
 * on one dashboard saw three typefaces. This is the same class of failure as
 * the emerald drift — a documented intention that never reached the screen — so
 * it gets the same treatment.
 */
class TypographyTest extends TestCase
{
    private const FONT = 'public/fonts/inter-latin-wght-normal.woff2';
    private const LICENCE = 'public/fonts/OFL.txt';

    private function appCss(): string
    {
        return (string) file_get_contents(resource_path('css/app.css'));
    }

    private function config(): string
    {
        return (string) file_get_contents(base_path('tailwind.config.js'));
    }

    #[Test]
    public function the_font_is_self_hosted_and_present(): void
    {
        // Self-hosted, not a CDN: this is an assessment product, and a
        // third-party font origin costs a DNS + TLS round trip on first paint
        // plus a third-party request on every exam start.
        $path = base_path(self::FONT);
        $this->assertFileExists($path, 'The brand font is not self-hosted. Do not fall back to the OS stack.');

        $bytes = (string) file_get_contents($path);
        $this->assertSame('wOF2', substr($bytes, 0, 4), 'Expected a woff2 file.');

        $this->assertFileExists(
            base_path(self::LICENCE),
            'Inter is licensed under the SIL Open Font License 1.1. The licence text must ship with the font.'
        );
    }

    #[Test]
    public function the_font_is_a_single_variable_file_covering_every_weight(): void
    {
        $css = $this->appCss();

        $this->assertStringContainsString('@font-face', $css);
        $this->assertStringContainsString("font-family: 'InterVariable'", $css);
        $this->assertStringContainsString("url('/fonts/inter-latin-wght-normal.woff2')", $css);

        // One file for the whole axis. The app uses four weight utilities, so a
        // static-face setup would mean four requests for the same screen.
        $this->assertMatchesRegularExpression(
            '/font-weight:\s*100\s+900/',
            $css,
            'The woff2 is a variable font; declare the full weight axis so one file serves every weight.'
        );
    }

    #[Test]
    public function the_font_declares_swap_and_a_latin_subset(): void
    {
        $css = $this->appCss();

        $this->assertStringContainsString('font-display: swap', $css, 'Without swap, text is invisible until the font lands.');

        $this->assertStringContainsString(
            'unicode-range:',
            $css,
            'A subsetted font must declare its unicode-range so non-latin text falls back instead of rendering as notdef boxes.'
        );
    }

    #[Test]
    public function the_font_is_preloaded_with_crossorigin(): void
    {
        $blade = (string) file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString('rel="preload"', $blade);
        $this->assertStringContainsString('as="font"', $blade);
        $this->assertStringContainsString('type="font/woff2"', $blade);
        $this->assertStringContainsString('inter-latin-wght-normal.woff2', $blade);

        // Font fetches are always CORS-mode, so the preload is wasted without it.
        $this->assertStringContainsString(
            'crossorigin',
            $blade,
            'A font preload without crossorigin is fetched twice and never reused.'
        );
    }

    #[Test]
    public function tailwind_resolves_the_stack_through_one_custom_property(): void
    {
        $this->assertStringContainsString('--pm-font-sans', $this->appCss());
        $this->assertStringContainsString('--pm-font-sans', $this->config());

        // The raw-CSS fault screen is rendered outside React, so it cannot use a
        // utility class. It must resolve through the same variable or the two
        // drift apart -- which is exactly how it ended up on an unbranded stack.
        $fault = (string) file_get_contents(resource_path('css/fault.css'));
        $this->assertStringContainsString(
            'var(--pm-font-sans',
            $fault,
            'fault.css must use the shared font variable, not its own stack.'
        );
    }

    #[Test]
    public function the_font_family_has_a_readable_fallback(): void
    {
        // An unresolved var() falls back to the browser default, which is serif.
        // The utility must carry its own fallback list.
        $this->assertStringContainsString(
            'var(--pm-font-sans, ui-sans-serif',
            $this->config(),
            'fontFamily.sans needs a var() fallback so text stays sans-serif if the defining stylesheet is dropped.'
        );
    }

    #[Test]
    public function no_screen_hardcodes_an_os_only_stack(): void
    {
        $offenders = [];

        foreach (glob(resource_path('css').'/*.css') as $file) {
            $contents = (string) file_get_contents($file);
            // fault.css is allowed the var(); a literal Segoe-first stack is not.
            if (preg_match_all('/font-family:\s*ui-sans-serif[^;]*/', $contents, $m)) {
                foreach ($m[0] as $hit) {
                    $offenders[basename($file)] = ($offenders[basename($file)] ?? 0) + 1;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'A literal system stack in CSS bypasses the brand font. Use var(--pm-font-sans). Found: '.json_encode($offenders)
        );
    }

    #[Test]
    public function live_figures_use_tabular_numerals(): void
    {
        // G-14: the exam timer changes every second and the report tables are
        // scanned column-wise, so proportional figures make digits jitter and
        // break vertical alignment.
        $exam = (string) file_get_contents(resource_path('js/Pages/Passimark/Exam.jsx'));
        $this->assertStringContainsString(
            'tabular-nums',
            $exam,
            'The exam timer must use tabular-nums or the MM:SS readout jitters every tick.'
        );

        $statStrip = (string) file_get_contents(resource_path('js/Pages/Passimark/Reports/StatStrip.jsx'));
        $this->assertStringContainsString('tabular-nums', $statStrip);

        $tables = 0;
        foreach (glob(resource_path('js/Pages/Passimark/Reports').'/*.jsx') as $file) {
            $contents = (string) file_get_contents($file);
            $tables += preg_match_all('/<table className="[^"]*tabular-nums/', $contents);
        }

        $this->assertGreaterThanOrEqual(
            10,
            $tables,
            "Expected the report tables carrying score/theta/duration columns to set tabular-nums, found $tables."
        );
    }
}
