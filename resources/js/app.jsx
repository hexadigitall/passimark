import ErrorBoundary from './Components/ErrorBoundary';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import '../css/fault.css';

const mount = document.getElementById('app');

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
  const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

  createInertiaApp({
    resolve: (name) => {
      const page = pages[`./Pages/${name}.jsx`];

      if (!page) {
        throw new Error(`Inertia page not found: ${name}`);
      }

      return page.default;
    },
    setup({ el, App, props }) {
      createRoot(el).render(<ErrorBoundary><App {...props} /></ErrorBoundary>);
    },
  });
} catch (error) {
  showFault(error);
}
