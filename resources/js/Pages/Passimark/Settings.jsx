import { Head, router, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';

export default function Settings({ user, preferences = {} }) {
  const notifications = preferences.notifications || {};
  const { data, setData, patch, processing } = useForm({
    notifications: {
      session_reminders: !!notifications.session_reminders,
      approval_updates: !!notifications.approval_updates,
      assessment_deadlines: !!notifications.assessment_deadlines,
      weekly_digest: !!notifications.weekly_digest,
    },
    theme: preferences.theme || 'dark',
    language: preferences.language || 'en',
  });

  const update = (key, value) => setData('notifications', { ...data.notifications, [key]: value });

  const submit = (e) => {
    e.preventDefault();
    patch(route('settings.update'), {
      preserveScroll: true,
      onSuccess: () => window.alert('Preferences saved.'),
      onError: () => window.alert('Could not save preferences.'),
    });
  };

  return (
    <DashboardLayout>
      <Head title="Settings" />

      <form onSubmit={submit} className="mx-auto max-w-5xl space-y-6">
        <div className="rounded-2xl border border-slate-700 bg-slate-800 p-6">
          <p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">Preferences</p>
          <h2 className="mt-2 text-3xl font-bold text-white">Account Settings</h2>
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          <div className="rounded-2xl border border-slate-700 bg-slate-800 p-6">
            <h3 className="text-lg font-semibold text-white">Notifications</h3>
            <div className="mt-5 space-y-4 text-sm text-slate-300">
              {[
                ['session_reminders', 'Session reminders'],
                ['approval_updates', 'Approval updates'],
                ['assessment_deadlines', 'Assessment deadlines'],
                ['weekly_digest', 'Weekly progress digest'],
              ].map(([key, label]) => (
                <label key={key} className="flex items-center justify-between rounded-xl border border-slate-700 bg-slate-900 px-4 py-3">
                  <span>{label}</span>
                  <input
                    type="checkbox"
                    checked={!!data.notifications[key]}
                    onChange={(e) => update(key, e.target.checked)}
                    className="h-4 w-4 accent-emerald-500"
                  />
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
                <select
                  value={data.theme}
                  onChange={(e) => setData('theme', e.target.value)}
                  className="mt-2 w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none"
                >
                  <option value="dark">Dark mode</option>
                  <option value="light">Light mode</option>
                  <option value="system">System default</option>
                </select>
              </div>
              <div className="rounded-xl border border-slate-700 bg-slate-900 p-4">
                <p className="text-slate-400">Language</p>
                <select
                  value={data.language}
                  onChange={(e) => setData('language', e.target.value)}
                  className="mt-2 w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none"
                >
                  <option value="en">English</option>
                  <option value="es">Spanish</option>
                  <option value="fr">French</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div className="flex justify-end">
          <button type="submit" disabled={processing} className="rounded-lg bg-emerald-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400 disabled:opacity-50">{processing ? 'Saving...' : 'Save preferences'}</button>
        </div>
      </form>
    </DashboardLayout>
  );
}