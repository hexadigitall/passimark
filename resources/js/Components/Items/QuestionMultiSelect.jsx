import React from 'react';
import { Strikethrough } from 'lucide-react';

export default function QuestionMultiSelect({
  options = [],
  selections = [],
  onChange,
  strikes = new Set(),
  onToggleStrike,
  minSelections = null,
  maxSelections = null,
  disabled = false,
}) {
  const toggleKey = (key) => {
    if (disabled) return;
    if (selections.includes(key)) {
      onChange(selections.filter((k) => k !== key));
    } else {
      if (maxSelections && selections.length >= maxSelections) {
        return;
      }
      onChange([...selections, key].sort());
    }
  };

  return (
    <div className="space-y-3">
      {(minSelections || maxSelections) && (
        <p className="text-xs font-medium text-brand-400">
          {maxSelections && minSelections === maxSelections
            ? `Select exactly ${maxSelections} choices (${selections.length}/${maxSelections} selected)`
            : `Select ${minSelections ? `at least ${minSelections}` : ''} ${
                maxSelections ? `up to ${maxSelections}` : ''
              } choices (${selections.length} selected)`}
        </p>
      )}

      {options.map((option) => {
        const isSelected = selections.includes(option.key);
        const isStruck = strikes.has(option.key);

        return (
          <div
            key={option.key}
            className={`group flex items-center justify-between rounded-xl border p-4 transition ${
              isSelected
                ? 'border-brand-500 bg-brand-600/15 text-slate-100 shadow-sm ring-1 ring-brand-500'
                : 'border-slate-800 bg-slate-900/80 text-slate-200 hover:border-slate-700 hover:bg-slate-900'
            } ${disabled ? 'pointer-events-none opacity-60' : ''}`}
          >
            <label
              htmlFor={`multi-option-${option.key}`}
              className="flex flex-1 cursor-pointer items-center gap-4"
            >
              <input
                id={`multi-option-${option.key}`}
                type="checkbox"
                name={`multi_choice_${option.key}`}
                value={option.key}
                checked={isSelected}
                onChange={() => toggleKey(option.key)}
                disabled={disabled}
                className="h-4 w-4 rounded accent-brand-500"
              />
              <span className="font-mono text-sm font-semibold text-brand-400">
                {option.key}.
              </span>
              <span
                className={`flex-1 text-sm leading-relaxed ${
                  isStruck ? 'text-slate-500 line-through' : ''
                }`}
              >
                {option.text}
              </span>
            </label>

            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                onToggleStrike(option.key);
              }}
              className={`rounded p-2 text-xs transition ${
                isStruck
                  ? 'text-brand-300'
                  : 'text-slate-500 hover:text-slate-200'
              }`}
              title="Strike through this option (elimination aid)"
              aria-label={`Strike through option ${option.key}`}
            >
              <Strikethrough className="h-4 w-4" />
            </button>
          </div>
        );
      })}
    </div>
  );
}
