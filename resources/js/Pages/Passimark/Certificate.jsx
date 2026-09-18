import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { Award, BadgeCheck, ShieldCheck, TrendingUp } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import Breadcrumbs from '../../Components/Breadcrumbs';

export default function Certificate({ certificate, user = {} }) {
  if (!certificate) {
    return (
      <DashboardLayout>
        <Head title="Certificate" />
        <section className="mx-auto max-w-3xl rounded-2xl border border-slate-700 bg-slate-900 p-10 text-center">
          <Award className="mx-auto h-10 w-10 text-slate-500" />
          <h2 className="mt-4 text-xl font-semibold text-white">Certificate not available</h2>
          <p className="mt-2 text-sm text-slate-400">This credential could not be found or is no longer valid.</p>
          <Link href="/profile" className="mt-6 inline-block rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950">
            Back to profile
          </Link>
        </section>
      </DashboardLayout>
    );
  }

  const percent = certificate.pass_probability != null ? Math.round(certificate.pass_probability * 1000) / 10 : null;
  const issued = certificate.certified_at ? new Date(certificate.certified_at) : null;

  const crumbs = [
    { label: 'Dashboard', href: '/' },
    { label: 'Profile', href: '/profile' },
    { label: 'Certificate' },
  ];

  return (
    <DashboardLayout>
      <Head title="Certificate" />
      <section className="mx-auto max-w-4xl">
        <Breadcrumbs items={crumbs} />

        <article className="overflow-hidden rounded-3xl border border-emerald-500/30 bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950/40 p-8 shadow-2xl">
          <div className="flex flex-wrap items-start justify-between gap-6">
            <div>
              <p className="flex items-center gap-2 font-mono text-xs uppercase tracking-[0.3em] text-emerald-400">
                <ShieldCheck className="h-4 w-4" /> Passimark Certified
              </p>
              <h1 className="mt-3 text-3xl font-bold text-white">{certificate.certification}</h1>
              {certificate.variant_label && <p className="mt-1 text-sm text-emerald-200/80">{certificate.variant_label}</p>}
              <p className="mt-6 text-sm uppercase tracking-wider text-slate-500">Awarded to</p>
              <p className="text-2xl font-semibold text-white">{user.name}</p>
            </div>

            <div className="rounded-2xl bg-white p-3 shadow-lg">
              <QRCodeSVG value={certificate.verify_url} size={132} level="M" />
              <p className="mt-2 max-w-[132px] text-center font-mono text-[10px] leading-tight text-slate-700">Scan to verify</p>
            </div>
          </div>

          <div className="mt-8 grid gap-3 sm:grid-cols-3">
            <Metric label="Ability (θ)" value={Number(certificate.theta ?? 0).toFixed(2)} />
            <Metric label="Pass probability" value={percent != null ? `${percent}%` : '—'} />
            <Metric label="Issued" value={issued ? issued.toLocaleDateString() : '—'} />
          </div>

          <div className="mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-slate-700/60 pt-6">
            <div>
              <p className="text-xs uppercase tracking-wider text-slate-500">Credential ID</p>
              <p className="font-mono text-sm text-emerald-200">{certificate.credential_id}</p>
            </div>
            <div className="flex items-center gap-2">
              <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-medium text-emerald-300">
                <BadgeCheck className="h-3.5 w-3.5" /> Verifiable
              </span>
              <Link
                href={certificate.verify_url}
                className="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-3 py-2 text-xs font-medium text-slate-200 transition hover:bg-slate-700"
              >
                <TrendingUp className="h-3.5 w-3.5" /> Verify
              </Link>
            </div>
          </div>
        </article>

        <p className="mt-4 break-all px-1 font-mono text-[11px] text-slate-600">{certificate.hash}</p>
      </section>
    </DashboardLayout>
  );
}

function Metric({ label, value }) {
  return (
    <div className="rounded-xl border border-slate-700 bg-slate-950/60 p-4">
      <p className="text-xs text-slate-500">{label}</p>
      <p className="mt-2 text-xl font-semibold text-white">{value}</p>
    </div>
  );
}
