import React from 'react';
import { Head } from '@inertiajs/react';
import { BadgeCheck, ShieldAlert, Award } from 'lucide-react';

export default function Verify({ valid = false, credentialId = '', certificate = null }) {
  const issued = certificate?.issued_at ? new Date(certificate.issued_at) : null;
  const percent = certificate?.pass_probability != null ? Math.round(certificate.pass_probability * 1000) / 10 : null;

  return (
    <>
      <Head title="Verify credential" />
      <main className="flex min-h-screen items-center justify-center bg-slate-950 px-6 py-16 text-white">
        <section className="w-full max-w-xl">
          {valid ? (
            <div className="rounded-3xl border border-emerald-500/30 bg-slate-900 p-8 text-center">
              <BadgeCheck className="mx-auto h-12 w-12 text-emerald-400" />
              <p className="mt-4 text-sm font-medium uppercase tracking-[0.25em] text-emerald-400">Credential verified</p>
              <h1 className="mt-2 text-2xl font-bold text-white">{certificate.certification}</h1>
              {certificate.variant_label && <p className="mt-1 text-sm text-slate-400">{certificate.variant_label}</p>}

              <dl className="mt-8 space-y-3 text-left text-sm">
                <Row label="Awarded to" value={certificate.holder} />
                <Row label="Credential ID" value={certificate.credential_id} mono />
                <Row label="Issued" value={issued ? issued.toLocaleDateString() : '—'} />
                <Row label="Ability (θ)" value={Number(certificate.theta ?? 0).toFixed(2)} />
                <Row label="Pass probability" value={percent != null ? `${percent}%` : '—'} />
              </dl>

              <p className="mt-6 break-all font-mono text-[11px] text-slate-600">{certificate.hash}</p>
            </div>
          ) : (
            <div className="rounded-3xl border border-slate-700 bg-slate-900 p-8 text-center">
              <ShieldAlert className="mx-auto h-12 w-12 text-amber-400" />
              <p className="mt-4 text-sm font-medium uppercase tracking-[0.25em] text-amber-300">No valid credential</p>
              <h1 className="mt-2 text-2xl font-bold text-white">We could not verify this code</h1>
              <p className="mt-3 text-sm text-slate-400">
                No Passimark credential matches <span className="font-mono text-slate-200">{credentialId}</span>.
              </p>
            </div>
          )}

          <p className="mt-6 flex items-center justify-center gap-2 text-xs text-slate-600">
            <Award className="h-3.5 w-3.5" /> Passimark verifiable credentials
          </p>
        </section>
      </main>
    </>
  );
}

function Row({ label, value, mono = false }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-slate-800 pb-3">
      <dt className="text-slate-500">{label}</dt>
      <dd className={mono ? 'font-mono text-emerald-200' : 'font-medium text-white'}>{value ?? '—'}</dd>
    </div>
  );
}
