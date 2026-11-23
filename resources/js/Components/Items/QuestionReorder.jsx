import React from 'react';
import { ArrowDown, ArrowUp, Check, GripVertical, Plus, RotateCcw, Trash2 } from 'lucide-react';

export default function QuestionReorder({
  payload = {},
  value = [],
  onChange,
  disabled = false,
}) {
  const availableItems = payload.available_items || [];
  const sequence = Array.isArray(value) ? value : [];

  // Lookup map for fast item text retrieval
  const itemMap = new Map(availableItems.map((item) => [item.id, item]));

  // Available items not yet placed into the ordered sequence
  const placedIds = new Set(sequence);
  const unplacedItems = availableItems.filter((item) => !placedIds.has(item.id));

  const handleAddItem = (itemId) => {
    if (disabled || placedIds.has(itemId)) return;
    onChange([...sequence, itemId]);
  };

  const handleRemoveItem = (index) => {
    if (disabled) return;
    const next = sequence.filter((_, idx) => idx !== index);
    onChange(next);
  };

  const handleMoveUp = (index) => {
    if (disabled || index === 0) return;
    const next = [...sequence];
    const temp = next[index - 1];
    next[index - 1] = next[index];
    next[index] = temp;
    onChange(next);
  };

  const handleMoveDown = (index) => {
    if (disabled || index === sequence.length - 1) return;
    const next = [...sequence];
    const temp = next[index + 1];
    next[index + 1] = next[index];
    next[index] = temp;
    onChange(next);
  };

  const handleReset = () => {
    if (disabled) return;
    onChange([]);
  };

  const handleDragStart = (e, itemId) => {
    if (disabled) return;
    e.dataTransfer.setData('text/plain', itemId);
  };

  const handleDropOnTarget = (e) => {
    e.preventDefault();
    if (disabled) return;
    const itemId = e.dataTransfer.getData('text/plain');
    if (itemId && !placedIds.has(itemId) && itemMap.has(itemId)) {
      onChange([...sequence, itemId]);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between text-xs text-slate-400">
        <p>
          Select items from the bank and arrange them into the correct procedural sequence.
        </p>
        {sequence.length > 0 && !disabled && (
          <button
            type="button"
            onClick={handleReset}
            className="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-slate-100 transition"
          >
            <RotateCcw className="h-3 w-3" /> Reset Sequence
          </button>
        )}
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        {/* Available Items Pool */}
        <section className="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
          <div className="flex items-center justify-between">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">
              Available Steps ({unplacedItems.length})
            </h3>
            <span className="text-[11px] text-slate-500">Tap to add</span>
          </div>

          <div className="mt-3 space-y-2">
            {unplacedItems.length === 0 ? (
              <div className="rounded-lg border border-dashed border-slate-800 py-8 text-center text-xs text-slate-500">
                All steps have been moved to the sequence.
              </div>
            ) : (
              unplacedItems.map((item) => (
                <div
                  key={item.id}
                  draggable={!disabled}
                  onDragStart={(e) => handleDragStart(e, item.id)}
                  onClick={() => handleAddItem(item.id)}
                  className="group flex cursor-pointer items-center justify-between rounded-lg border border-slate-700 bg-slate-800 p-3 text-sm font-medium text-slate-200 transition hover:border-brand-500/50 hover:bg-slate-750"
                >
                  <div className="flex items-center gap-2">
                    <GripVertical className="h-4 w-4 text-slate-500 group-hover:text-slate-400" />
                    <span>{item.text}</span>
                  </div>
                  <button
                    type="button"
                    disabled={disabled}
                    className="rounded p-1 text-slate-400 group-hover:text-brand-300 transition"
                    title="Add to sequence"
                  >
                    <Plus className="h-4 w-4" />
                  </button>
                </div>
              ))
            )}
          </div>
        </section>

        {/* Target Ordered Sequence */}
        <section
          onDragOver={(e) => e.preventDefault()}
          onDrop={handleDropOnTarget}
          className="rounded-xl border border-slate-800 bg-slate-900/60 p-4"
        >
          <div className="flex items-center justify-between">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">
              Ordered Sequence ({sequence.length})
            </h3>
            <span className="text-[11px] text-brand-300">
              {sequence.length > 0 ? `${sequence.length} steps configured` : 'Drag or add steps here'}
            </span>
          </div>

          <div className="mt-3 space-y-2">
            {sequence.length === 0 ? (
              <div className="rounded-lg border border-dashed border-slate-700 bg-slate-950/40 py-10 text-center text-xs text-slate-500">
                Click or drag steps from the left to build the sequence.
              </div>
            ) : (
              sequence.map((itemId, idx) => {
                const item = itemMap.get(itemId);
                if (!item) return null;

                return (
                  <div
                    key={`${itemId}-${idx}`}
                    className="flex items-center justify-between rounded-lg border border-brand-500/40 bg-slate-800/90 p-3 text-sm shadow-sm transition"
                  >
                    <div className="flex items-center gap-3">
                      <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-500/20 text-xs font-bold text-brand-300">
                        {idx + 1}
                      </span>
                      <span className="font-medium text-slate-100">{item.text}</span>
                    </div>

                    <div className="flex items-center gap-1 shrink-0">
                      <button
                        type="button"
                        onClick={() => handleMoveUp(idx)}
                        disabled={disabled || idx === 0}
                        className="rounded p-1 text-slate-400 hover:bg-slate-700 hover:text-slate-100 disabled:opacity-30 disabled:hover:bg-transparent"
                        title="Move Up"
                      >
                        <ArrowUp className="h-4 w-4" />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleMoveDown(idx)}
                        disabled={disabled || idx === sequence.length - 1}
                        className="rounded p-1 text-slate-400 hover:bg-slate-700 hover:text-slate-100 disabled:opacity-30 disabled:hover:bg-transparent"
                        title="Move Down"
                      >
                        <ArrowDown className="h-4 w-4" />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleRemoveItem(idx)}
                        disabled={disabled}
                        className="rounded p-1 text-slate-400 hover:bg-red-500/20 hover:text-red-300 transition"
                        title="Remove from sequence"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </section>
      </div>
    </div>
  );
}
