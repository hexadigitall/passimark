export default function Auth({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'auth';
  const next = funnel?.next;

  return (
    <div className="passimark-funnel passimark-funnel--auth">
      <div className="passimark-funnel__glass">
        <h1 className="passimark-funnel__title">Confirm it&apos;s you</h1>
        <p className="passimark-funnel__body">
          The ladder already knows who you are. This rung is a real credential gate — sign in with
          the account that holds your Passimark progress, and the next rung unlocks.
        </p>
        <ol className="passimark-funnel__ladder">
          {ladder.map((rung, i) => (
            <li
              key={rung}
              className={rung === step ? 'passimark-funnel__rung passimark-funnel__rung--current' : 'passimark-funnel__rung'}
            >
              <span className="passimark-funnel__rung-num">{i + 1}</span>
              <span className="passimark-funnel__rung-name">{rung}</span>
            </li>
          ))}
        </ol>
        {next ? (
          <a className="passimark-funnel__cta" href={next}>
            Sign in &amp; continue
          </a>
        ) : null}
      </div>
    </div>
  );
}
