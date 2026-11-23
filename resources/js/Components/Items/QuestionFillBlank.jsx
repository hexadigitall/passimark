import React from 'react';
import { Delete, X } from 'lucide-react';

export default function QuestionFillBlank({
  payload = {},
  value = '',
  onChange,
  disabled = false,
}) {
  const textValue = typeof value === 'string' ? value : (value?.text || '');
  const inputType = payload.input_type === 'number' ? 'number' : 'text';

  const handleChange = (e) => {
    if (disabled) return;
    onChange(e.target.value);
  };

  const handleClear = () => {
    if (disabled) return;
    onChange('');
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between text-xs text-slate-400">
        <p>Type your answer in the field below. Ensure correct syntax and spelling.</p>
        {textValue && !disabled && (
          <button
            type="button"
            onClick={handleClear}
            className="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-slate-100 transition"
          >
            <X className="h-3 w-3" /> Clear
          </button>
        )}
      </div>

      <div className="relative">
        <input
          type={inputType}
          value={textValue}
          onChange={handleChange}
          disabled={disabled}
          placeholder="Enter exact response..."
          className="w-full rounded-xl border border-slate-700 bg-slate-900/90 px-4 py-3.5 text-base font-mono text-slate-100 placeholder-slate-500 shadow-inner transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40 disabled:cursor-not-allowed disabled:bg-slate-950"
        />
        {textValue.length > 0 && (
          <span className="absolute right-3.5 top-3.5 text-xs font-mono text-slate-400">
            {textValue.length} chars
          </span>
        )}
      </div>

      <p className="text-xs text-slate-500">
        Note: Exact matching, casing, and whitespace normalization apply as configured by the assessment rubric.
      </p>
    </div>
  );
}
