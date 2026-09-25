export default function Dashboard({ funnel }) {
  const ladder =
    funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'dashboard';
  const resume = funnel?.resume;
  const favorites = funnel?.favorites ?? [];
  const nextRequired = funnel?.nextRequired;

  return (
    <div className="passimark-dashboard passimark-dashboard--lean">
      <div className="passimark-dashboard__hero">
        <p className="passimark-dashboard__eyebrow">Your track is unlocked · rung 7 of 7</p>
        <h1 className="passimark-dashboard__title">
          {resume ? 'Welcome back — pick up where you left off' : 'Your track is ready'}
        </h1>
        {resume && (
          <p className="passimark-dashboard__resume">
            <strong>{resume.title}</strong> · {resume.phase} (session {resume.session})
          </p>
        )}
        {nextRequired && (
          <p className="passimark-dashboard__next">
            Next required: <strong>{nextRequired.title}</strong>
          </p>
        )}
      </div>

      {favorites.length > 0 && (
        <section className="passimark-dashboard__favorites" aria-label="Favorites">
          <h2 className="passimark-dashboard__section">Your favorites</h2>
          <ul className="passimark-dashboard__favorite-list">
            {favorites.map((fav, i) => (
              <li key={fav.track ?? i} className="passimark-dashboard__favorite">
                {fav.title}
              </li>
            ))}
          </ul>
        </section>
      )}

      <ol className="passimark-dashboard__ladder" aria-label="Track ladder">
        {ladder.map((rung, i) => (
          <li
            key={rung}
            className={
              rung === step
                ? 'passimark-dashboard__rung passimark-dashboard__rung--current'
                : 'passimark-dashboard__rung'
            }
          >
            <span className="passimark-dashboard__rung-num">{i + 1}</span>
            <span className="passimark-dashboard__rung-name">{rung}</span>
          </li>
        ))}
      </ol>
    </div>
  );
}
