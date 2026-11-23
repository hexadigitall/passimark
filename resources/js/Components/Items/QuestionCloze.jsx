import React from 'react';
import { HelpCircle } from 'lucide-react';

export default function QuestionCloze({
  payload = {},
  value = {},
  onChange,
  disabled = false,
}) {
  const template = payload.template || '';
  const blanks = payload.blanks || {};
  const currentAnswers = typeof value === 'object' && value !== null ? value : {};

  const handleSelect = (blankId, selectedOption) => {
    if (disabled) return;
    onChange({
      ...currentAnswers,
      [blankId]: selectedOption,
    });
  };

  // Split template by tokens of form {{blank_id}}
  const parts = template.split(/(\{\{[^}]+\}\})/g);
  const blankKeys = Object.keys(blanks);
  const answeredCount = blankKeys.filter((key) => Boolean(currentAnswers[key])).length;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between text-xs text-slate-400">
        <p>Select the correct term for each drop-down blank in the text below.</p>
        <span className="font-medium text-brand-300">
          {answeredCount} of {blankKeys.length} blanks answered
        </span>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/60 p-6 leading-relaxed text-slate-200 text-base md:text-lg">
        {parts.map((part, idx) => {
          const match = part.match(/^\{\{([^}]+)\}\}$/);
          if (match) {
            const blankId = match[1].trim();
            const blankConfig = blanks[blankId];

            if (!blankConfig) {
              return (
                <span key={idx} className="text-red-400 font-mono text-xs">
                  [{blankId}]
                </span>
              );
            }

            const options = blankConfig.options || [];
            const selectedVal = currentAnswers[blankId] || '';

            return (
              <span key={idx} className="inline-block my-1 mx-1.5 align-middle">
                <select
                  value={selectedVal}
                  onChange={(e) => handleSelect(blankId, e.target.value)}
                  disabled={disabled}
                  aria-label={`Blank ${blankId}`}
                  className={`rounded-lg border px-3 py-1.5 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-brand-500 ${
                    selectedVal
                      ? 'border-brand-500/60 bg-brand-500/15 text-slate-100'
                      : 'border-slate-700 bg-slate-800 text-slate-300 hover:border-slate-600'
                  }`}
                >
                  <option value="" disabled className="bg-slate-900 text-slate-500">
                    -- Select --
                  </option>
                  {options.map((opt, optIdx) => (
                    <option key={optIdx} value={opt} className="bg-slate-900 text-slate-100">
                      {opt}
                    </option>
                  ))}
                </select>
              </span>
            );
          }

          return <span key={idx}>{part}</span>;
        })}
      </div>
    </div>
  );
}
