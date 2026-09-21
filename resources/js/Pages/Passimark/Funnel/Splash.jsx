export default function Splash({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'splash';
  const next = funnel?.next;

  return (
    <div className="passimark-funnel passimark-funnel--splash">
      <div className="passimark-funnel__glass">
        <div className="passimark-funnel__mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="72" height="72" fill="none" stroke="currentColor" strokeWidth="1.2">
            <path d="M4 12a8 8 0 1 0 16 0" />
            <path d="M12 4v8" />
            <circle cx="12" cy="8" r="1" fill="currentColor" />
          </svg>
        </div>
        <h1 className="passimark-funnel__title">Passimark</h1>
        <p className="passimark-funnel__tagline">Sprint 9.5 — content coherence, one credential story.</p>
        <ol className="passimark-funnel__ladder">
          {ladder.map((rung, i) => (
            <li key={rung} className={rung === step ? 'passimark-funnel__rung passimark-funnel__rung--current' : 'passimark-funnel__rung'}>
              <span className="passimark-funnel__rung-num">{i + 1}</span>
              <span className="passimark-funnel__rung-name">{rung}</span>
            </li>
          ))}
        </ol>
        {next ? (
          <a className="passimark-funnel__cta" href={next}>
            Continue
          </a>
        ) : null}
      </div>
    </div>
  );
}
