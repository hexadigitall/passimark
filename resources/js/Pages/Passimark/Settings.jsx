import { Head } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';

export default function Settings({ user }) {
  return (
    <DashboardLayout>
      <Head title="Settings" />

      <section className="mx-auto max-w-5xl space-y-6">
        <div className="rounded-2xl border border-slate-700 bg-slate-800 p-6">
          <p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">Preferences</p>
          <h2 className="mt-2 text-3xl font-bold text-white">Account Settings</h2>
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          <div className="rounded-2xl border border-slate-700 bg-slate-800 p-6">
            <h3 className="text-lg font-semibold text-white">Notifications</h3>
            <div className="mt-5 space-y-4 text-sm text-slate-300">
              {[
                'Session reminders',
                'Approval updates',
                'Assessment deadlines',
                'Weekly progress digest',
              ].map((item) => (
                <label key={item} className="flex items-center justify-between rounded-xl border border-slate-700 bg-slate-900 px-4 py-3">
                  <span>{item}</span>
                  <input type="checkbox" defaultChecked={item !== 'Assessment deadlines'} className="h-4 w-4 accent-emerald-500" />
                </label>
              ))}
            </div>
          </div>

          <div className="rounded-2xl border border-slate-700 bg-slate-800 p-6">
            <h3 className="text-lg font-semibold text-white">Profile preferences</h3>
            <div className="mt-5 space-y-4 text-sm text-slate-300">
              <div className="rounded-xl border border-slate-700 bg-slate-900 p-4">
                <p className="text-slate-400">Signed in as</p>
                <p className="mt-2 font-medium text-white">{user?.email}</p>
              </div>
              <div className="rounded-xl border border-slate-700 bg-slate-900 p-4">
                <p className="text-slate-400">Theme</p>
                <p className="mt-2 font-medium text-white">Dark mode</p>
              </div>
              <div className="rounded-xl border border-slate-700 bg-slate-900 p-4">
                <p className="text-slate-400">Language</p>
                <p className="mt-2 font-medium text-white">English</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </DashboardLayout>
  );
}