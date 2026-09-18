# Sprint 8 — Certificates, QR verification & completion

**Status: Complete** | Branch: `feature/sprint-7-5-content-coherence`

## Problem
A learner could pass a track's final assessment and the platform would mark the session
`completed`/`approved`, but nothing marked the *track* as finished and no credential was
produced. There was no certificate screen, no shareable proof, and no way for a third party to
confirm a credential.

## Issuance model
`App\Services\CertificateIssuer` is the single source of truth. A certificate is minted only for
sessions with `phase_type === 'final'` when:

- the learner's `ability_theta >= session.theta_required` (the final was actually passed), and
- status is `completed` (auto tracks) or `approved` (approval-gated tracks).

Issuance is idempotent: `issueIfEligible()` returns early when `certified_at` is already set, and
when the track's `advancement !== 'auto'` a `completed` final is deliberately **not** certified —
only the instructor approval path can mint it.

Hook points:
- `PassimarkController::finish()` — issues after a passed attempt (auto tracks resolve immediately).
- `PassimarkAdminController::approve()` — issues when an approval-gated final is approved; the JSON
  response now carries a `certificate` key.

## Credential shape
| Field | Value |
|---|---|
| `credential_id` | `PMK-{CERTCODE}-{YEAR}-{HEX8}` — deterministic per user+track |
| `credential_hash` | `sha256:` + digest of the credential id, user, session, θ, pass probability, and issue time (tamper-evident placeholder for a future Polygon/IPFS anchor) |
| `pass_probability` | 1PL logistic `1 / (1 + exp(-1.7 * (θ - θ_required)))`, rounded to 4dp |
| `certified_at` | issue timestamp |

`CERTCODE` is the track's `certKey()`/slug uppercased (non-alphanumerics stripped); `HEX8` is the
first eight hex characters of the user+track digest. Migration
`2026_01_01_000014_add_certification_to_progress` adds `certified_at`, `credential_id` (unique),
`pass_probability`, and `credential_hash` to `passimark_progress`.

## Surfaces
- **`GET /certificate/{progress}`** (`passimark.certificate`, auth) — owner-only (`404` for anyone
  else or an uncertified row). Renders `Passimark/Certificate`: awarded-to name, certification +
  variant, θ, pass probability, issued date, credential ID, and a `qrcode.react` `QRCodeSVG`
  encoding the public verify URL.
- **`GET /verify/{credentialId}`** (`passimark.verify`, public) — no authentication. Renders a
  standalone `Passimark/Verify` page that reports valid/invalid and echoes the holder, credential
  ID, issue date, θ, and pass probability. Unknown codes resolve to an invalid state, never an
  error.
- **Result** — a green "Certificate issued" banner with credential ID, pass probability, and a
  **View certificate** CTA whenever the result payload carries a certificate.
- **Track** — every session with `progress.credential_id` gains a **Certificate** link in its
  action row.
- **Profile** — a **Certificates** card lists every issued credential, linking to its certificate
  screen.

## PWA
`public/manifest.json` already shipped the `#0F172A` background in Sprint 7.5e; Sprint 8 adds a
regression test pinning the slate background + `standalone` display so the theme cannot drift.

## Tests
New `tests/Feature/CertificateIssuanceTest` (4 tests) covers auto-track issuance and credential
format, owner-only certificate access, public verify for valid/unknown codes, approval-gated
issuance only after instructor approval, and the PWA manifest.

Full suite **104 tests / 29,906 assertions** green; `npm run build` clean
(`app-BlcdREX9.js` 456.94 kB / 132.38 kB gzip); migration `000014` applied to the dev DB.
