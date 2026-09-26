import React from 'react';
import ErrorBoundary from './Components/ErrorBoundary';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import '../css/fault.css';

const mount = document.getElementById('app');

/** Branded splash shown while a lazily-loaded screen chunk is in flight. */
function BootScreen() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-950">
      <div className="flex flex-col items-center gap-3">
        <span className="h-8 w-8 animate-spin rounded-full border-2 border-slate-700 border-t-emerald-400" />
        <p className="text-sm text-slate-400">Loading Passimark...</p>
      </div>
    </div>
  );
}

function showFault(error) {
  if (!mount) {
    return;
  }

  const message = error && error.message ? error.message : String(error);

  mount.textContent = '';

  const panel = document.createElement('div');
  panel.className = 'passimark-app__fault';

  const eyebrow = document.createElement('p');
  eyebrow.className = 'passimark-app__fault-eyebrow';
  eyebrow.textContent = 'Passimark recovered the load';

  const title = document.createElement('h1');
  title.className = 'passimark-app__fault-title';
  title.textContent = 'This screen hit a runtime fault - it is never a blank page';

  const body = document.createElement('p');
  body.className = 'passimark-app__fault-body';
  body.textContent = message;

  const hint = document.createElement('p');
  hint.className = 'passimark-app__fault-hint';
  hint.textContent = 'The fault is named here on purpose so it can be fixed at its source - reload to try the rung again.';

  const reload = document.createElement('a');
  reload.className = 'passimark-app__fault-reload';
  reload.href = window.location.pathname;
  reload.textContent = 'Reload this rung';

  panel.append(eyebrow, title, body, hint, reload);
  mount.appendChild(panel);
}

window.addEventListener('error', (event) => showFault(event.error || event.message));
window.addEventListener('unhandledrejection', (event) => showFault(event.reason));

try {
  // Lazy, NOT eager. `eager: true` pulled all 28 screens into the single entry
  // chunk, so a learner downloading the login form also downloaded the exam
  // engine, the admin control center and the whole catalog. Rollup splits each
  // page into its own chunk and the browser fetches only the one it renders.
  // The lookup is kept synchronous by pre-loading the resolved page component,
  // which is the contract Inertia's `resolve` expects.
  const pages = import.meta.glob('./Pages/**/*.jsx');

  createInertiaApp({
    resolve: (name) => {
      const load = pages[`./Pages/${name}.jsx`];

      if (!load) {
        throw new Error(`Inertia page not found: ${name}`);
      }

      return load();
    },
    setup({ el, App, props }) {
      createRoot(el).render(
        <React.Suspense fallback={<BootScreen />}>
          <ErrorBoundary>
            <App {...props} />
          </ErrorBoundary>
        </React.Suspense>,
      );
    },
  });
} catch (error) {
  showFault(error);
}
