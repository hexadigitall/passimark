export default function Intro({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'intro';
const next = funnel?.next;

  const milestones = [
    {
      rung: 'intro',
      title: 'Two minutes changes your day',
      body: "This is not a wall of tiles and never will be. Seven honest rungs - lock, splash, how-it-works, who you are, your focus, your permissions, and the dashboard you deserve. Every advance is saved; the ladder never dead-ends.",
    },
    {
      rung: 'auth',
      title: 'Confirm who you are',
      body: "Your account is already real. The auth rung simply threads your existing credentials through - nothing is created, nothing extra is required.",
    },
    {
      rung: 'focus',
      title: 'Pick what front-and-center means',
      body: "One focus at a time. Choose the track you want to move now; it stays re-editable, so this is never a permanent decision.",
    },
    {
      rung: 'permissions',
      title: 'Set the guardrails yourself',
      body: "What you allow drives what you can reach. Permissions prime once, stays yours, and is always re-tunable from your dashboard.",
    },
  ];

  return (
    <div className="passimark-funnel passimark-funnel--intro">
      <div className="passimark-funnel__glass">
        <span className="passimark-funnel__eyebrow">Rung {ladder.indexOf(step) + 1} of {ladder.length} · {step}</span>
        <h1 className="passimark-funnel__title">How this works</h1>
        <p className="passimark-funnel__body">
          Two minutes changes your day. Confirm who you are, pick what to focus on, and set your
          permissions - then your dashboard is yours, ready to go. Every advance is saved; the
          ladder never dead-ends.
        </p>

        <ol className="passimark-funnel__milestones">
          {milestones.map((m, i) => (
            <li key={m.rung} className="passimark-funnel__milestone">
              <span className="passimark-funnel__milestone-num">{i + 1}</span>
              <div className="passimark-funnel__milestone-text">
                <strong className="passimark-funnel__milestone-title">{m.title}</strong>
                <span className="passimark-funnel__milestone-body">{m.body}</span>
              </div>
            </li>
          ))}
        </ol>

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
            Start the guide
          </a>
        ) : null}
      </div>
    </div>
  );
}
