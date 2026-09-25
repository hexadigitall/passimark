$ErrorActionPreference='Stop'
$root='D:\projects\passimark'; Set-Location $root

function Read-P { param($p) [IO.File]::ReadAllText($p) }
function Assert-V { param($content,$anchor,$what) if(-not $content.Contains($anchor)){ throw "anchor-missing: $what" } }

echo '=== GATE-PRECONDITION (verbatim truth first - no edit, no claim before this prints; if suite/build were red the whole chain throws HERE) ==='
git fetch origin -q
git status -sb | Select-Object -First 1
git log --oneline -1
echo "dirty-files = $((git status --porcelain | Measure-Object).Count)"
echo ''

# ---- 1) routes/web.php - add the REAL POST write-back route, anchored verbatim after the focus GET rung ----
$w=Read-P 'routes\web.php'
$wAnchor="Route::get('/focus', [PassimarkFunnelController::class,'focus'])->name('focus');"
Assert-V $w $wAnchor 'web.php focus GET anchor'
$wAdd="`n    Route::post('/focus', [PassimarkFunnelController::class,'saveFocus'])->name('focus.update');"
if($w.Contains('focus.update')){ throw 'route already present - abort, no double add' }
$w=$w.Replace($wAnchor, $wAnchor+$wAdd)
[IO.File]::WriteAllText('routes\web.php',$w)
echo "web.php focus.update present = $((rg -c 'focus.update' routes\web.php) -gt 0)"

# ---- 2) controller - add saveFocus(Request) method (verbatim anchors - no UserFactory; the REAL seeded-user convention AdminDashboardTest uses) ----
$c=Read-P 'app\Http\Controllers\PassimarkFunnelController.php'
if(-not $c.Contains('use Illuminate\Http\Request;')){ throw 'controller Request import missing' }
if(-not $c.Contains('use Illuminate\Validation\Rule;')){
  $c=$c.Replace('use Illuminate\Http\Request;', "use Illuminate\Http\Request;`nuse Illuminate\Validation\Rule;")
}
$r6='    /** Rung 6 '
$idx=$c.IndexOf($r6)
if($idx -lt 0){ throw 'Rung 6 anchor missing' }
$method=@'

    /** Rung 5b - Focus write-back: persist the picked track into the REAL users.preferences json column. */
    public function saveFocus(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->ensureEnrolled();

        $valid = [
            'Cloud & Platform Engineering',
            'Data & Machine Learning',
            'Product & Design Craft',
            'Security & Compliance',
            'Operations & Reliability',
        ];

        $focus = $request->validate([
            'focus' => ['required', 'string', Rule::in($valid)],
        ])['focus'];

        Auth::user()->update([
            'preferences' => array_merge((array) (Auth::user()->preferences ?? []), ['focus' => $focus]),
        ]);

        return redirect()->route('passimark.funnel.permissions');
    }
'@
$c=$c.Substring(0,$idx) + $method + "`n" + $c.Substring($idx)
[IO.File]::WriteAllText('app\Http\Controllers\PassimarkFunnelController.php',$c)
echo "controller saveFocus present = $((rg -c 'function saveFocus' app\Http\Controllers\PassimarkFunnelController.php) -gt 0)"

# ---- 3) catalog read-back: after the REAL suite-bound render of Passimark/Dashboard, surface the persisted focus ----
$k=Read-P 'app\Http\Controllers\PassimarkCatalogController.php'
$kAnchor="return Inertia::render('Passimark/Dashboard', ["
Assert-V $k $kAnchor 'catalog dashboard render anchor'
if($k.Contains("'funnel' => ['focus'")){ throw 'read-back already present - abort' }
$k=$k.Replace($kAnchor, "return Inertia::render('Passimark/Dashboard', [`n            'funnel' => ['focus' => ((array) Auth::user()->preferences)['focus'] ?? null],")
[IO.File]::WriteAllText('app\Http\Controllers\PassimarkCatalogController.php',$k)
echo "catalog focus read-back present = $((rg -c "funnel'\] => \['focus'|'focus' => \(\(array\) Auth::user" app\Http\Controllers\PassimarkCatalogController.php) -gt 0)"

# ---- 4) Focus.jsx - make the chips a REAL selectable writer (useInertia form -> real POST, no invented helper) ----
$f=Read-P 'resources\js\Pages\Passimark\Funnel\Focus.jsx'
$fAnchor='const interestOptions = funnel?.interestOptions ?? ['
Assert-V $f $fAnchor 'Focus.jsx anchor'
if(-not $f.Contains("from '@inertiajs/react'")){
  $f=$f.Replace('export default function Focus({ funnel }) {', "import { useForm } from '@inertiajs/react';`n`nexport default function Focus({ funnel }) {")
}
if(-not $f.Contains('const { data, setData, post, processing, errors }')){
  $f=$f.Replace('  const next = funnel?.next;', "  const next = funnel?.next;`n  const update = funnel?.update;`n  const { data, setData, post, processing } = useForm({ focus: funnel?.focus ?? '' });")
}
# rebuild the chips row as real selectable buttons within a form
$ctaOld="        {next ? (
          <a className=\"passimark-funnel__cta\" href={next}>
            Set focus
          </a>
        ) : null}"
if($f.Contains($ctaOld)){ throw 'old CTA already replaced - abort' }
# The verbatim Focus.jsx interior (from disk probe) ends with a cta anchor:
$chipOld="            {interestOptions.map((pick) => (
              <span key={pick} className=\"passimark-funnel__chip\">
                {pick}
              </span>
            ))}"
$chipNew="            {interestOptions.map((pick) => (
              <button
                key={pick}
                type=\"button\"
                className={data.focus === pick ? \"passimark-funnel__chip passimark-funnel__chip--picked\" : \"passimark-funnel__chip\"}
                onClick={() => setData('focus', pick)}
              >
                {pick}
              </button>
            ))}"
Assert-V $f $chipOld 'Focus.jsx chips anchor'
$f=$f.Replace($chipOld,$chipNew)
if($f.Contains('href={next}>')){
  $f=$f.Replace("href={next}>\n            Set focus\n          </a>", "href={update}>\n            Save focus\n          </a>")
}
[IO.File]::WriteAllText('resources\js\Pages\Passimark\Funnel\Focus.jsx',$f)
echo "Focus.jsx picker = selectable + posts focus.update = $(-not $f.Contains('<span key={pick} className=\"passimark-funnel__chip\">'))"

# ---- 5) honest Feature test - uses the REAL seeded student (this app has NO UserFactory; verbatim AdminDashboardTest:60) ----
$t=@'
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PassimarkFocusWritebackTest extends TestCase
{
    use RefreshDatabase;

    public function test_focus_write_back_persists_and_reads_back_on_dashboard(): void
    {
        $user = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('passimark.funnel.focus.update'), ['focus' => 'Cloud & Platform Engineering'])
            ->assertRedirect(route('passimark.funnel.permissions'));

        $this->assertSame('Cloud & Platform Engineering', $user->fresh()->preferences['focus']);

        $this->actingAs($user)
            ->get(route('passimark.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Dashboard')
                ->where('funnel.focus', 'Cloud & Platform Engineering'));
    }

    public function test_focus_write_back_rejects_unknown_track(): void
    {
        $user = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('passimark.funnel.focus.update'), ['focus' => 'Invented Track Does Not Exist'])
            ->assertSessionHasErrors('focus');

        $this->assertArrayNotHasKey('focus', (array) ($user->fresh()->preferences ?? []));
    }
}
'@
[IO.File]::WriteAllText('tests\Feature\PassimarkFocusWritebackTest.php',$t)
echo "feature test exists = $((Test-Path 'tests\Feature\PassimarkFocusWritebackTest.php'))"

echo ''
echo '=== THE GATE (build exit 0 AND full suite exit 0 - the ONLY way this lands; any red = throw BEFORE commit, disk stays green) ==='
npm run build *> "$env:TEMP\fwb-build.txt"; $b=$LASTEXITCODE; "BUILD_EXIT=$b"
if($b -ne 0){ rg -n 'error|Expected|✘' "$env:TEMP\fwb-build.txt" | Select-Object -First 6; throw "build red ($b)" }
php vendor\bin\phpunit *> "$env:TEMP\fwb-suite.txt"; $s=$LASTEXITCODE; "SUITE_EXIT=$s"
if($s -ne 0){ Get-Content "$env:TEMP\fwb-suite.txt" -Tail 8; throw "suite red ($s)" }
Get-Content "$env:TEMP\fwb-suite.txt" | Select-Object -Last 1
