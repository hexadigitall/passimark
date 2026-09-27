/**
 * First-run progress indicator.
 *
 * Deliberately wordless. The ladder entries are internal route identifiers
 * (the same slugs that name these controllers' methods) and must never be
 * rendered as user-facing text — a learner has no idea what a permissions step
 * or a splash step is. The sequence is expressed by routing; this only answers
 * "how much is left".
 *
 * Position stays available to assistive tech through the progressbar role, so no
 * information is lost by keeping developer vocabulary off the screen.
 */
export default function FunnelProgress({ ladder = [], step, className = '' }) {
  const index = Array.isArray(ladder) ? ladder.indexOf(step) : -1;

  if (index < 0 || !ladder.length) {
    return null;
  }

  const current = index + 1;
  const total = ladder.length;

  return (
    <div className={className}>
      <div
        role="progressbar"
        aria-valuemin={1}
        aria-valuemax={total}
        aria-valuenow={current}
        aria-label={`Step ${current} of ${total}`}
        className="h-1 w-full overflow-hidden rounded-full bg-slate-700/60"
      >
        <div
          className="h-full rounded-full bg-brand-500 transition-[width] duration-300 ease-out motion-reduce:transition-none"
          style={{ width: `${(current / total) * 100}%` }}
        />
      </div>
    </div>
  );
}
