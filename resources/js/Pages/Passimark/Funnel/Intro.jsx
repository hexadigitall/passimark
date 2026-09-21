export default function Intro({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'intro';
  const next = funnel?.next;

  return (
    <div className="passimark-funnel passimark-funnel--intro">
      <div className="passimark-funnel__glass">
        <h1 className="passimark-funnel__title">How this works</h1>
        <p className="passimark-funnel__body">
          Two minutes changes your day. Confirm who you are, pick what to focus on, and set your
          permissions — then your dashboard is yours, ready to go. Every advance is saved; the
          ladder never dead-ends.
        </p>
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
            Got it — next rung
          </a>
        ) : null}
      </div>
    </div>
  );
}
