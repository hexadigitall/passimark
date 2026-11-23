import React, { useState } from 'react';
import { ArrowRight, Check, GripVertical, RotateCcw } from 'lucide-react';

export default function QuestionDragDrop({
  payload = {},
  value = {},
  onChange,
  disabled = false,
}) {
  const sources = payload.sources || [];
  const targets = payload.targets || [];
  const [activeSourceId, setActiveSourceId] = useState(null);

  // Sources that have not been placed yet
  const assignedSourceIds = new Set(Object.values(value));
  const availableSources = sources.filter((s) => !assignedSourceIds.has(s.id));

  const handleSourceClick = (sourceId) => {
    if (disabled) return;
    setActiveSourceId(activeSourceId === sourceId ? null : sourceId);
  };

  const handleTargetClick = (targetId) => {
    if (disabled) return;
    if (activeSourceId) {
      onChange({ ...value, [targetId]: activeSourceId });
      setActiveSourceId(null);
    } else if (value[targetId]) {
      // Remove assignment from this target
      const next = { ...value };
      delete next[targetId];
      onChange(next);
    }
  };

  const handleDragStart = (e, sourceId) => {
    if (disabled) return;
    e.dataTransfer.setData('text/plain', sourceId);
  };

  const handleDrop = (e, targetId) => {
    e.preventDefault();
    if (disabled) return;
    const sourceId = e.dataTransfer.getData('text/plain');
    if (sourceId) {
      onChange({ ...value, [targetId]: sourceId });
    }
  };

  const resetAll = () => {
    if (disabled) return;
    onChange({});
    setActiveSourceId(null);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between text-xs text-slate-400">
        <p>
          Drag items into target slots or tap a source card then tap a destination slot.
        </p>
        {Object.keys(value).length > 0 && !disabled && (
          <button
            type="button"
            onClick={resetAll}
            className="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-white"
          >
            <RotateCcw className="h-3 w-3" /> Reset
          </button>
        )}
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        {/* Source Cards Bank */}
        <section className="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
          <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">
            Available Items ({availableSources.length})
          </h3>
          <div className="mt-3 space-y-2">
            {availableSources.length === 0 ? (
              <p className="py-6 text-center text-xs text-slate-500">
                All items have been assigned.
              </p>
            ) : (
              availableSources.map((source) => {
                const isSelected = activeSourceId === source.id;

                return (
                  <div
                    key={source.id}
                    draggable={!disabled}
                    onDragStart={(e) => handleDragStart(e, source.id)}
                    onClick={() => handleSourceClick(source.id)}
                    className={`flex cursor-pointer items-center justify-between rounded-lg border p-3 text-sm font-medium transition ${
                      isSelected
                        ? 'border-brand-400 bg-brand-600/20 text-slate-100 shadow-md ring-2 ring-brand-400'
                        : 'border-slate-700 bg-slate-800 text-slate-200 hover:border-slate-600 hover:bg-slate-750'
                    }`}
                  >
                    <div className="flex items-center gap-2">
                      <GripVertical className="h-4 w-4 text-slate-500" />
                      <span>{source.label}</span>
                    </div>
                    {isSelected && <ArrowRight className="h-4 w-4 text-brand-300 animate-pulse" />}
                  </div>
                );
              })
            )}
          </div>
        </section>

        {/* Target Slots */}
        <section className="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
          <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">
            Target Categories ({targets.length})
          </h3>
          <div className="mt-3 space-y-3">
            {targets.map((target) => {
              const assignedSourceId = value[target.id];
              const assignedSource = sources.find((s) => s.id === assignedSourceId);

              return (
                <div
                  key={target.id}
                  onDragOver={(e) => e.preventDefault()}
                  onDrop={(e) => handleDrop(e, target.id)}
                  onClick={() => handleTargetClick(target.id)}
                  className={`flex cursor-pointer flex-col justify-between rounded-lg border p-3 transition ${
                    assignedSource
                      ? 'border-brand-500/60 bg-brand-500/10'
                      : activeSourceId
                      ? 'border-dashed border-brand-400/80 bg-brand-500/5 hover:bg-brand-500/10'
                      : 'border-dashed border-slate-700 bg-slate-950/60 hover:border-slate-600'
                  }`}
                >
                  <div className="flex items-center justify-between text-xs text-slate-400">
                    <span className="font-semibold text-slate-300">{target.slot_label}</span>
                    {assignedSource ? (
                      <span className="inline-flex items-center gap-1 text-[11px] text-brand-300">
                        <Check className="h-3 w-3" /> Assigned
                      </span>
                    ) : (
                      <span className="text-[11px] text-slate-500">Drop here</span>
                    )}
                  </div>

                  <div className="mt-2 min-h-8">
                    {assignedSource ? (
                      <div className="flex items-center justify-between rounded bg-slate-800 px-3 py-1.5 text-sm font-medium text-white shadow-sm">
                        <span>{assignedSource.label}</span>
                        {!disabled && (
                          <span className="text-xs text-slate-400 hover:text-red-300">
                            Remove
                          </span>
                        )}
                      </div>
                    ) : (
                      <div className="flex h-8 items-center justify-center rounded border border-dashed border-slate-800 text-xs text-slate-600">
                        Empty slot
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </section>
      </div>
    </div>
  );
}
