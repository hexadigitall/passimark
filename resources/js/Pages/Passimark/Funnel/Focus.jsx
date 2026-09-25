export default function Focus({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'focus';
  const next = funnel?.next;

  return (
    <div className="passimark-funnel passimark-funnel--focus">
      <div className="passimark-funnel__glass">
        <h1 className="passimark-funnel__title">Choose your focus</h1>
        <p className="passimark-funnel__body">
          One focus at a time. Pick the track you want front and center right now — it stays
          re-editable, so this is never a permanent decision.
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
            Set focus
          </a>
        ) : null}
      </div>
    </div>
  );
}
