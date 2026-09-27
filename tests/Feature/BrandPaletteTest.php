<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the brand palette against the drift that shipped once already.
 *
 * docs/v4-worldwide-catalog-spec.md §2.2 specifies four tokens — Primary #1A9E2D,
 * Deep #0F5D2F, Accent #7CFC8F, Dark #0F172A. Those tokens existed in
 * tailwind.config.js from Sprint 7 and were referenced zero times; the UI fell
 * back to stock Tailwind emerald at hue 160-163° while the spec's green sits at
 * 129°. Two hue families, and nothing failed to catch it.
 *
 * These tests make the palette a contract rather than a comment.
 */
class BrandPaletteTest extends TestCase
{
    /** Spec §2.2. The palette the product is supposed to have. */
    private const SPEC = [
        'primary' => '#1A9E2D',
        'deep' => '#0F5D2F',
        'accent' => '#7CFC8F',
        'dark' => '#0F172A',
    ];

    /**
     * The scale anchored on the spec, mirroring tailwind.config.js.
     * brand-600 is Primary and brand-800 is Deep by construction.
     */
    private const RAMP = [
        50 => '#F0FBF1', 100 => '#DCF7DE', 200 => '#B8EDBC', 300 => '#7BD98F',
        400 => '#52C86B', 500 => '#2FB84A', 600 => '#1A9E2D', 700 => '#157A24',
        800 => '#0F5D2F', 900 => '#0B4522', 950 => '#06240F',
    ];

    /** @return array<int, string> every Tailwind class token in resources/ */
    private function brandClasses(): array
    {
        $found = [];

        foreach ($this->sourceFiles() as $file) {
            preg_match_all('/\bbrand-\d{3}\b/', file_get_contents($file), $m);
            $found = array_merge($found, $m[0]);
        }

        return array_values(array_unique($found));
    }

    /** @return array<int, string> */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['js', 'css'] as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path($dir), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if ($f->isFile() && in_array($f->getExtension(), ['js', 'jsx', 'css'], true)) {
                    $files[] = $f->getPathname();
                }
            }
        }

        $files[] = resource_path('views/app.blade.php');

        return $files;
    }

    private function config(): string
    {
        return (string) file_get_contents(base_path('tailwind.config.js'));
    }

    #[Test]
    public function no_stock_emerald_classes_remain(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);
            // \b-anchored so brand-500 is not a false positive, and so a
            // legitimate "emerald" mention in a comment or string still trips.
            if (preg_match_all('/(?<![\w-])emerald-\d{3}\b/', $contents, $m)) {
                foreach ($m[0] as $hit) {
                    $offenders[$hit] = ($offenders[$hit] ?? 0) + 1;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Stock Tailwind emerald is back in the UI (hue 160-163 vs the spec's 129). "
            ."Use brand-* instead. Found: ".json_encode($offenders)
        );
    }

    #[Test]
    public function no_stock_emerald_hexes_are_hardcoded_in_css(): void
    {
        // A class-name retint cannot see raw hex. fault.css hardcoded emerald
        // #10b981 and #34d399, so the exam fault screen stayed emerald through a
        // 253-class rewrite -- and the built CSS still shipped #10B981 while the
        // source looked clean. Assert on the hexes too.
        $emeraldHexes = [
            '10b981' => 'emerald-500', '34d399' => 'emerald-400',
            '6ee7b7' => 'emerald-300', 'a7f3d0' => 'emerald-200',
            '059669' => 'emerald-600', '047857' => 'emerald-700',
            '065f46' => 'emerald-800', '064e3b' => 'emerald-900',
            'd1fae5' => 'emerald-100',
        ];

        $offenders = [];

        foreach (glob(resource_path('css').'/*.css') as $file) {
            $contents = (string) file_get_contents($file);
            foreach ($emeraldHexes as $hex => $token) {
                if (preg_match_all('/#'.$hex.'\b/i', $contents, $m)) {
                    $offenders[basename($file).' #'.$hex.' ('.$token.')'] = count($m[0]);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Raw emerald hexes bypass the brand scale. Use a brand-* class, or a hex from the documented scale. Found: '.json_encode($offenders)
        );
    }

    #[Test]
    public function the_brand_scale_is_actually_used(): void
    {
        $used = $this->brandClasses();

        $this->assertNotEmpty($used, 'The brand scale is defined but nothing references it — the same dead-config failure as pm.brand.');

        // A retint that collapsed everything onto one step would still pass the
        // test above, so require the scale to actually be exercised.
        $steps = array_unique(array_map(static fn (string $c): int => (int) substr($c, 6), $used));
        $this->assertGreaterThanOrEqual(
            5,
            count($steps),
            'Expected the brand scale to be used across several steps, got: '.implode(', ', array_map(static fn (int $s): string => "brand-{$s}", $steps))
        );
    }

    #[Test]
    public function the_brand_scale_anchors_on_the_spec_tokens(): void
    {
        $config = $this->config();

        $this->assertStringContainsString(
            "600: '".self::SPEC['primary']."'",
            $config,
            'brand-600 must be the spec Primary #1A9E2D. It cannot be the 500 step: as a button fill with slate-950 ink it only reaches 5.74:1, whereas brand-500 reaches 7.76:1.'
        );

        $this->assertStringContainsString(
            "800: '".self::SPEC['deep']."'",
            $config,
            'brand-800 must be the spec Deep #0F5D2F. It is also the PWA icon field.'
        );

        $this->assertStringContainsString("accent: '".self::SPEC['accent']."'", $config);
        $this->assertStringContainsString("deep: '".self::SPEC['dark']."'", $config);
    }

    #[Test]
    public function every_brand_step_is_legible_or_declared_fill_only(): void
    {
        $surface = self::SPEC['dark'];

        foreach (self::RAMP as $step => $hex) {
            $ratio = $this->contrast($hex, $surface);

            // Steps 50-600 carry text. 700 is AA-large. 800+ are fill/border
            // only by design and are asserted as such rather than left to chance.
            $floor = $step <= 600 ? 4.5 : ($step === 700 ? 3.0 : 0.0);

            $this->assertGreaterThanOrEqual(
                $floor,
                $ratio,
                sprintf('brand-%d (%s) is only %.2f:1 on %s; it needs at least %.1f:1 to carry a label.', $step, $hex, $ratio, $surface, $floor)
            );
        }
    }

    #[Test]
    public function steps_800_and_deeper_never_carry_text(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);
            // text-brand-800/900/950, or border/ring/divide on them, are all
            // fine; only a *text* colour on a fill-only step is a defect.
            if (preg_match_all('/text-brand-(800|900|950)\b/', $contents, $m)) {
                foreach ($m[0] as $hit) {
                    $offenders[$hit] = ($offenders[$hit] ?? 0) + 1;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'brand-800 and deeper fall below 3:1 on the slate surface and are fill/border only. Found text usage: '.json_encode($offenders)
        );
    }

    #[Test]
    public function the_pwa_manifest_reports_the_spec_colours(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true);

        $this->assertSame(self::SPEC['primary'], strtoupper((string) $manifest['theme_color']));
        $this->assertSame(self::SPEC['dark'], strtoupper((string) $manifest['background_color']));

        $blade = (string) file_get_contents(resource_path('views/app.blade.php'));
        $this->assertStringContainsString(
            'name="theme-color" content="'.self::SPEC['primary'].'"',
            $blade,
            'The blade meta theme-color must match the manifest or installed chrome disagrees with the app.'
        );
    }

    #[Test]
    public function the_icon_field_sits_on_the_spec_deep_green(): void
    {
        // The shipped icon field is #08582F against the spec Deep #0F5D2F. That
        // is a dRGB delta of (7, 5, 0) — under 3% on any channel, and 4.6° of
        // hue. It is deliberately left as-is: rewriting nine binaries for a
        // difference nobody can see is churn, and the icon was never the split.
        // What matters is that it stays in the brand family, so assert the
        // tolerance rather than an exact value.
        $icon = public_path('images/passimark/passimark_icon_512x512.png');
        $this->assertFileExists($icon);

        $field = $this->greenFieldColour($icon);

        $this->assertNotNull($field, 'Could not read the icon field colour.');

        [$r, $g, $b] = $this->rgb($field);
        [$sr, $sg, $sb] = $this->rgb(self::SPEC['deep']);

        $this->assertLessThanOrEqual(
            10,
            max(abs($r - $sr), abs($g - $sg), abs($b - $sb)),
            sprintf('Icon field %s has drifted from the spec Deep %s. It is a brand asset — recolour deliberately, not by accident.', $field, self::SPEC['deep'])
        );
    }

    #[Test]
    public function buttons_on_a_brand_fill_use_dark_ink(): void
    {
        // White on a mid-green is the classic dark-theme mistake. brand-500 is
        // 2.5:1 against white but 7.76:1 against slate-950, so every solid brand
        // fill in the app must carry dark ink.
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);
            if (preg_match_all('/bg-brand-500\b[^"\']{0,200}?\btext-white\b/', $contents, $m)) {
                foreach ($m[0] as $hit) {
                    $offenders[basename($file)] = ($offenders[basename($file)] ?? 0) + 1;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'White ink on a brand-500 fill is ~2.5:1. Use text-slate-950 (7.76:1). Found: '.json_encode($offenders)
        );
    }

    // --- helpers -----------------------------------------------------------

    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function relativeLuminance(string $hex): float
    {
        $channel = static function (int $v): float {
            $c = $v / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        [$r, $g, $b] = $this->rgb($hex);

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    private function contrast(string $a, string $b): float
    {
        $la = $this->relativeLuminance($a);
        $lb = $this->relativeLuminance($b);
        $hi = max($la, $lb);
        $lo = min($la, $lb);

        return ($hi + 0.05) / ($lo + 0.05);
    }

    /**
     * Most common *green* colour in a PNG, as #RRGGBB.
     *
     * Filters to greenish pixels rather than taking the overall mode, because
     * the silver mark covers more area than the field in the larger sizes --
     * the overall mode of the 512 icon is the mark (#E3E3E3), not the field.
     *
     * Decoded by hand rather than via ext-gd: gd is not a composer requirement,
     * so a gd-based assertion would silently skip on any environment that is
     * missing it — including CI — and quietly stop guarding anything. Handles
     * 8-bit non-interlaced greyscale/RGB/grey+alpha/RGBA, which is what the
     * shipped icon set is (colour type 6).
     */
    private function greenFieldColour(string $path): ?string
    {
        $data = @file_get_contents($path);
        if ($data === false || substr((string) $data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        $channels = [0 => 1, 2 => 3, 4 => 2, 6 => 4];
        $offset = 8;
        $idat = '';
        $header = null;

        while ($offset < strlen($data) - 8) {
            $length = unpack('N', substr($data, $offset, 4))[1];
            $type = substr($data, $offset + 4, 4);

            if ($type === 'IHDR') {
                $header = unpack('Nw/Nh/Cbd/Cct/Ccomp/Cfilt/Cinter', substr($data, $offset + 8, 13));
            } elseif ($type === 'IDAT') {
                $idat .= substr($data, $offset + 8, $length);
            } elseif ($type === 'IEND') {
                break;
            }

            $offset += 12 + $length;
        }

        if ($header === null
            || $header['bd'] !== 8
            || $header['inter'] !== 0
            || ! isset($channels[$header['ct']])) {
            return null; // palette or interlaced: not present in this asset set
        }

        $raw = @zlib_decode($idat);
        if ($raw === false) {
            return null;
        }

        $bpp = $channels[$header['ct']];
        $width = $header['w'];
        $height = $header['h'];
        $stride = $width * $bpp;
        $previous = array_fill(0, $stride, 0);        $counts = [];
        $cursor = 0;

        for ($y = 0; $y < $height; $y++) {
            $filter = ord($raw[$cursor]);
            $cursor++;
            $line = substr($raw, $cursor, $stride);
            $cursor += $stride;

            // Undo the per-scanline filter (PNG spec §9.2). Work in a plain int
            // array: PHP string offsets hold single bytes, so the arithmetic
            // has to happen on ord() results rather than on the string itself.
            $current = [];

            for ($i = 0; $i < $stride; $i++) {
                $raw_byte = ord($line[$i]);
                $a = $i >= $bpp ? $current[$i - $bpp] : 0;
                $b = $previous[$i];
                $c = $i >= $bpp ? $previous[$i - $bpp] : 0;

                $value = match ($filter) {
                    0 => $raw_byte,
                    1 => $raw_byte + $a,
                    2 => $raw_byte + $b,
                    3 => $raw_byte + intdiv($a + $b, 2),
                    4 => $raw_byte + $this->paeth($a, $b, $c),
                    default => null,
                };

                if ($value === null) {
                    return null; // unknown filter
                }

                $current[$i] = $value & 0xFF;
            }

            $previous = $current;

            for ($x = 0; $x < $width; $x += 2) { // sample every other pixel
                $p = $x * $bpp;
                $alpha = $bpp === 4 ? $current[$p + 3] : 255;
                $r = $current[$p];
                $g = $current[$p + 1];
                $b = $current[$p + 2];

                if ($alpha < 170) { // ignore the transparent surround
                    continue;
                }

                if ($g <= $r || $g <= $b) { // the field is the green, not the mark
                    continue;
                }

                $hex = sprintf('#%02X%02X%02X', $r, $g, $b);
                $counts[$hex] = ($counts[$hex] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return array_key_first($counts);
    }

    private function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }

        return $pb <= $pc ? $b : $c;
    }
}
