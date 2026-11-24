import React, { useState } from 'react';
import { 
  Plus, Trash2, Save, Download, Eye, Layers, Sliders, CheckSquare, 
  HelpCircle, Sparkles, FileText, UploadCloud 
} from 'lucide-react';
import QuestionMultiSelect from '../../Components/Items/QuestionMultiSelect';
import QuestionDragDrop from '../../Components/Items/QuestionDragDrop';
import QuestionHotspot from '../../Components/Items/QuestionHotspot';
import QuestionReorder from '../../Components/Items/QuestionReorder';

export default function DesktopDesigner() {
  const [questions, setQuestions] = useState([
    {
      id: 'd-1',
      type: 'single_choice',
      prompt: 'What layer of the OSI model does TLS encryption operate at?',
      domain: 'Network Security',
      correct_key: 'B',
      options: [
        { key: 'A', text: 'Network Layer (Layer 3)', is_correct: false },
        { key: 'B', text: 'Presentation / Transport Layer (Layer 5-6/4)', is_correct: true },
        { key: 'C', text: 'Data Link Layer (Layer 2)', is_correct: false },
      ],
      explanation: 'TLS operates primarily above the Transport layer and provides presentation security services.',
      irt_a: 1.8,
      irt_b: 0.2,
      irt_c: 0.25,
    },
  ]);
  const [selectedIdx, setSelectedIdx] = useState(0);

  const activeQ = questions[selectedIdx] || questions[0];

  const updateActiveQ = (field, value) => {
    setQuestions((prev) => {
      const copy = [...prev];
      copy[selectedIdx] = { ...copy[selectedIdx], [field]: value };
      return copy;
    });
  };

  const addOption = () => {
    const nextKey = String.fromCharCode(65 + (activeQ.options?.length || 0));
    const nextOptions = [...(activeQ.options || []), { key: nextKey, text: '', is_correct: false }];
    updateActiveQ('options', nextOptions);
  };

  const removeOption = (idx) => {
    const nextOptions = activeQ.options.filter((_, i) => i !== idx);
    updateActiveQ('options', nextOptions);
  };

  const addNewQuestion = () => {
    const newQ = {
      id: `d-${Date.now()}`,
      type: 'single_choice',
      prompt: 'New Question Title / Problem Scenario...',
      domain: 'General Assessment',
      correct_key: 'A',
      options: [
        { key: 'A', text: 'First option', is_correct: true },
        { key: 'B', text: 'Second option', is_correct: false },
      ],
      explanation: 'Normative explanation rationale...',
      irt_a: 1.5,
      irt_b: 0.0,
      irt_c: 0.25,
    };
    setQuestions((prev) => [...prev, newQ]);
    setSelectedIdx(questions.length);
  };

  const handleExportJson = () => {
    const dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(questions, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute('href', dataStr);
    downloadAnchor.setAttribute('download', 'passimark_authored_bundle.json');
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
  };

  return (
    <div className="flex flex-1 overflow-hidden bg-slate-950 text-slate-100">
      {/* Left Column: Question Outline / List */}
      <div className="w-72 shrink-0 border-r border-slate-800 bg-slate-900/60 p-4 flex flex-col justify-between overflow-y-auto">
        <div>
          <div className="flex items-center justify-between text-xs font-semibold text-slate-300 mb-3">
            <span>Authored Item Bank ({questions.length})</span>
            <button
              onClick={addNewQuestion}
              className="flex items-center gap-1 rounded bg-brand-500 px-2 py-1 text-[11px] font-bold text-slate-950 hover:bg-brand-400"
            >
              <Plus className="h-3 w-3" />
              <span>Add Item</span>
            </button>
          </div>

          <div className="flex flex-col gap-2">
            {questions.map((q, idx) => {
              const isSelected = idx === selectedIdx;
              return (
                <div
                  key={q.id}
                  onClick={() => setSelectedIdx(idx)}
                  className={`flex cursor-pointer items-center justify-between rounded-lg border p-2.5 text-xs transition-colors ${
                    isSelected
                      ? 'border-brand-500 bg-brand-500/10 text-slate-100'
                      : 'border-slate-800 bg-slate-900/40 text-slate-400 hover:border-slate-700 hover:text-slate-200'
                  }`}
                >
                  <div className="flex items-center gap-2 truncate">
                    <span className="font-mono text-[10px] text-slate-500">#{idx + 1}</span>
                    <span className="truncate">{q.prompt || 'Untitled Question'}</span>
                  </div>
                  <span className="shrink-0 rounded bg-slate-800 px-1.5 py-0.5 text-[9px] text-brand-400 font-mono">
                    {q.type.replace('_', ' ')}
                  </span>
                </div>
              );
            })}
          </div>
        </div>

        <button
          onClick={handleExportJson}
          className="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-slate-800 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition-colors border border-slate-700"
        >
          <Download className="h-3.5 w-3.5 text-brand-400" />
          <span>Export .psmk JSON</span>
        </button>
      </div>

      {/* Middle Column: WYSIWYG Form Inputs */}
      <div className="flex-1 overflow-y-auto border-r border-slate-800 p-6">
        <div className="max-w-2xl mx-auto flex flex-col gap-5">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-bold uppercase tracking-wider text-slate-200">
              Edit Question #{selectedIdx + 1}
            </h2>
            <div className="flex items-center gap-2">
              <label className="text-xs text-slate-400">Type:</label>
              <select
                value={activeQ.type}
                onChange={(e) => updateActiveQ('type', e.target.value)}
                className="rounded bg-slate-900 px-2.5 py-1 text-xs text-slate-200 border border-slate-800 focus:outline-none focus:border-brand-500"
              >
                <option value="single_choice">Single Choice (1-of-N)</option>
                <option value="multiple_choice">Multiple Choice (K-of-N)</option>
                <option value="drag_drop">Drag & Drop</option>
                <option value="hotspot">Hotspot Diagram</option>
                <option value="build_list">Build List / Reorder</option>
                <option value="cloze">Dropdown Cloze</option>
                <option value="fill_blank">Fill in the Blank</option>
              </select>
            </div>
          </div>

          {/* Prompt */}
          <div>
            <label className="block text-xs font-medium text-slate-300 mb-1">
              Question Scenario / Prompt
            </label>
            <textarea
              value={activeQ.prompt || ''}
              onChange={(e) => updateActiveQ('prompt', e.target.value)}
              rows={4}
              className="w-full rounded-lg bg-slate-900 p-3 text-sm text-slate-100 border border-slate-800 focus:outline-none focus:border-brand-500"
              placeholder="Type problem description or scenario..."
            />
          </div>

          {/* Options (for single & multiple choice) */}
          {(activeQ.type === 'single_choice' || activeQ.type === 'multiple_choice') && (
            <div>
              <div className="flex items-center justify-between text-xs font-medium text-slate-300 mb-2">
                <span>Options & Key</span>
                <button
                  type="button"
                  onClick={addOption}
                  className="text-brand-400 hover:underline text-xs flex items-center gap-1"
                >
                  <Plus className="h-3 w-3" /> Add Choice
                </button>
              </div>

              <div className="flex flex-col gap-2">
                {activeQ.options?.map((opt, i) => (
                  <div key={opt.key || i} className="flex items-center gap-2">
                    <span className="h-7 w-7 rounded bg-slate-800 flex items-center justify-center font-mono text-xs font-bold text-slate-300">
                      {opt.key}
                    </span>
                    <input
                      type="text"
                      value={opt.text || ''}
                      onChange={(e) => {
                        const copy = [...activeQ.options];
                        copy[i] = { ...copy[i], text: e.target.value };
                        updateActiveQ('options', copy);
                      }}
                      className="flex-1 rounded bg-slate-900 px-3 py-1.5 text-xs text-slate-200 border border-slate-800 focus:outline-none focus:border-brand-500"
                      placeholder={`Choice ${opt.key} description...`}
                    />
                    <input
                      type="radio"
                      name="correct_choice"
                      checked={activeQ.correct_key === opt.key}
                      onChange={() => updateActiveQ('correct_key', opt.key)}
                      title="Mark as correct answer"
                      className="cursor-pointer"
                    />
                    <button
                      type="button"
                      onClick={() => removeOption(i)}
                      className="text-slate-600 hover:text-rose-400 p-1"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Psychometrics IRT 3PL Panel */}
          <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
            <h3 className="text-xs font-semibold text-slate-300 flex items-center gap-1.5 mb-3">
              <Sliders className="h-3.5 w-3.5 text-brand-400" />
              <span>IRT 3PL Psychometric Calibration</span>
            </h3>

            <div className="grid grid-cols-3 gap-4 text-xs">
              <div>
                <label className="text-slate-400">Discrimination (a): {activeQ.irt_a ?? 1.5}</label>
                <input
                  type="range"
                  min="0.1"
                  max="3.0"
                  step="0.05"
                  value={activeQ.irt_a ?? 1.5}
                  onChange={(e) => updateActiveQ('irt_a', parseFloat(e.target.value))}
                  className="w-full mt-1 accent-brand-500"
                />
              </div>

              <div>
                <label className="text-slate-400">Difficulty (b): {activeQ.irt_b ?? 0.0}</label>
                <input
                  type="range"
                  min="-3.0"
                  max="3.0"
                  step="0.1"
                  value={activeQ.irt_b ?? 0.0}
                  onChange={(e) => updateActiveQ('irt_b', parseFloat(e.target.value))}
                  className="w-full mt-1 accent-brand-500"
                />
              </div>

              <div>
                <label className="text-slate-400">Guessing (c): {activeQ.irt_c ?? 0.25}</label>
                <input
                  type="range"
                  min="0.0"
                  max="0.5"
                  step="0.05"
                  value={activeQ.irt_c ?? 0.25}
                  onChange={(e) => updateActiveQ('irt_c', parseFloat(e.target.value))}
                  className="w-full mt-1 accent-brand-500"
                />
              </div>
            </div>
          </div>

          {/* Rationale & Explanation */}
          <div>
            <label className="block text-xs font-medium text-slate-300 mb-1">
              Normative Explanation & Reference
            </label>
            <textarea
              value={activeQ.explanation || ''}
              onChange={(e) => updateActiveQ('explanation', e.target.value)}
              rows={3}
              className="w-full rounded-lg bg-slate-900 p-3 text-xs text-slate-200 border border-slate-800 focus:outline-none focus:border-brand-500"
              placeholder="Why the correct option is optimal..."
            />
          </div>
        </div>
      </div>

      {/* Right Column: Real-Time Instant Preview */}
      <div className="w-80 shrink-0 bg-slate-900/30 p-5 overflow-y-auto hidden xl:block">
        <div className="flex items-center gap-1.5 text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">
          <Eye className="h-3.5 w-3.5 text-brand-400" />
          <span>Live Rendering Preview</span>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-900/90 p-4">
          <span className="rounded bg-slate-800 px-2 py-0.5 text-[10px] text-brand-400 font-mono">
            Preview
          </span>
          <p className="mt-2 text-xs font-medium text-slate-100 leading-relaxed">
            {activeQ.prompt || 'Question prompt will render here...'}
          </p>

          <div className="mt-4 flex flex-col gap-2">
            {activeQ.options?.map((opt) => (
              <div
                key={opt.key}
                className={`flex items-center gap-2 rounded-lg border p-2 text-xs ${
                  activeQ.correct_key === opt.key
                    ? 'border-brand-500/50 bg-brand-500/10 text-brand-300'
                    : 'border-slate-800 bg-slate-950/60 text-slate-300'
                }`}
              >
                <span className="h-5 w-5 rounded bg-slate-800 flex items-center justify-center font-mono text-[10px] font-bold">
                  {opt.key}
                </span>
                <span className="truncate">{opt.text || `Option ${opt.key}`}</span>
              </div>
            ))}
          </div>

          {activeQ.explanation && (
            <div className="mt-4 border-t border-slate-800 pt-3 text-[11px] text-slate-400 leading-relaxed">
              <span className="font-semibold text-brand-400">Rationale: </span>
              {activeQ.explanation}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
