export default function Permissions({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'permissions';
  const next = funnel?.next;

  return (
    <div className="passimark-funnel passimark-funnel--permissions">
      <div className="passimark-funnel__glass">
        <h1 className="passimark-funnel__title">One permission to skip</h1>
        <p className="passimark-funnel__body">
          The last rung before your dashboard. It is skippable — if you already know your tracks let
          you move, press straight through to the command center.
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
            Skip to dashboard
          </a>
        ) : null}
      </div>
    </div>
  );
}
