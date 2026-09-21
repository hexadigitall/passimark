export default function Lock({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'lock';
  const next = funnel?.next;

  return (
    <div className="passimark-funnel passimark-funnel--lock">
      <div className="passimark-funnel__glass">
        <div className="passimark-funnel__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="1.5">
            <rect x="4" y="10" width="16" height="11" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            <circle cx="12" cy="15.5" r="1.6" />
          </svg>
        </div>
        <h1 className="passimark-funnel__title">Passimark</h1>
        <p className="passimark-funnel__sub">Your certification is locked. Let&apos;s open it together.</p>
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
            Start the ladder
          </a>
        ) : null}
      </div>
    </div>
  );
}
