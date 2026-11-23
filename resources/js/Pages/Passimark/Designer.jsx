import React, { useMemo, useState } from 'react';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowRight,
  BookOpen,
  Check,
  CheckCircle2,
  Code,
  Eye,
  FileText,
  FolderTree,
  GitBranch,
  HelpCircle,
  Image as ImageIcon,
  Layers,
  ListOrdered,
  Maximize2,
  Minimize2,
  MousePointerClick,
  Network,
  Plus,
  RotateCcw,
  Save,
  Sliders,
  Sparkles,
  Trash2,
  Type,
  X,
} from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import QuestionSingleChoice from '../../Components/Items/QuestionSingleChoice';
import QuestionMultiSelect from '../../Components/Items/QuestionMultiSelect';
import QuestionDragDrop from '../../Components/Items/QuestionDragDrop';
import QuestionHotspot from '../../Components/Items/QuestionHotspot';
import QuestionReorder from '../../Components/Items/QuestionReorder';
import QuestionCloze from '../../Components/Items/QuestionCloze';
import QuestionCreateTree from '../../Components/Items/QuestionCreateTree';
import QuestionFillBlank from '../../Components/Items/QuestionFillBlank';
import CaseStudyViewer from '../../Components/Exhibits/CaseStudyViewer';

export default function Designer({
  tracks = [],
  sessions = [],
  tags = [],
  editingQuestion = null,
}) {
  const isEditing = Boolean(editingQuestion?.id);

  // Core metadata
  const [sessionId, setSessionId] = useState(editingQuestion?.session_id || sessions[0]?.id || '');
  const [itemType, setItemType] = useState(editingQuestion?.type || 'single_choice');
  const [content, setContent] = useState(editingQuestion?.content || '');
  const [domain, setDomain] = useState(editingQuestion?.domain || '');
  const [bloomLevel, setBloomLevel] = useState(editingQuestion?.bloom_level || 'Apply');
  const [explanation, setExplanation] = useState(editingQuestion?.explanation || '');
  const [reference, setReference] = useState(editingQuestion?.reference || '');

  // Psychometrics (IRT 3PL)
  const [difficulty, setDifficulty] = useState(editingQuestion?.difficulty ?? 0.0);
  const [discrimination, setDiscrimination] = useState(editingQuestion?.discrimination ?? 1.2);
  const [guessing, setGuessing] = useState(editingQuestion?.guessing ?? (itemType === 'single_choice' ? 0.25 : 0.0));

  // Options for single_choice & multiple_choice
  const [options, setOptions] = useState(() => {
    if (editingQuestion?.options && editingQuestion.options.length > 0) {
      return editingQuestion.options;
    }
    return [
      { key: 'A', text: 'Option A description', is_correct: true },
      { key: 'B', text: 'Option B description', is_correct: false },
      { key: 'C', text: 'Option C description', is_correct: false },
      { key: 'D', text: 'Option D description', is_correct: false },
    ];
  });

  // Exhibits attached
  const [exhibits, setExhibits] = useState(() => editingQuestion?.exhibits || []);

  // Interactive Payload State per question type
  const [interactivePayload, setInteractivePayload] = useState(() => {
    if (editingQuestion?.interactive_payload) {
      return editingQuestion.interactive_payload;
    }
    return {};
  });

  // Drag & drop payload defaults
  const ddSources = interactivePayload.sources || [
    { id: 'src-1', label: 'Source Component 1' },
    { id: 'src-2', label: 'Source Component 2' },
  ];
  const ddTargets = interactivePayload.targets || [
    { id: 'tgt-1', slot_label: 'Target Slot 1', correct_source_id: 'src-1' },
    { id: 'tgt-2', slot_label: 'Target Slot 2', correct_source_id: 'src-2' },
  ];

  // Hotspot defaults
  const hsImageSrc = interactivePayload.image_src || '/images/passimark/passimark_icon_512x512.png';
  const hsShape = interactivePayload.target_shape || 'circle';
  const hsBounds = interactivePayload.target_bounds || { center_x: 256, center_y: 256, radius: 40 };

  // Build List defaults
  const blItems = interactivePayload.available_items || [
    { id: 'step-1', text: 'Phase 1: Initial Assessment' },
    { id: 'step-2', text: 'Phase 2: Execution & Deployment' },
    { id: 'step-3', text: 'Phase 3: Verification & Auditing' },
  ];
  const blSequence = interactivePayload.correct_sequence || ['step-1', 'step-2', 'step-3'];

  // Cloze defaults
  const clozeTemplate = interactivePayload.template || 'To achieve high availability, deploy in at least {{b1}} zones and attach an {{b2}}.';
  const clozeBlanks = interactivePayload.blanks || {
    b1: { options: ['Two', 'Three', 'Four'], correct_answer: 'Two' },
    b2: { options: ['ALB', 'IGW', 'VPG'], correct_answer: 'ALB' },
  };

  // Create Tree defaults
  const treeRoot = interactivePayload.root_label || 'Enterprise Security Infrastructure';
  const treeNodes = interactivePayload.available_nodes || [
    { id: 'n1', label: 'Perimeter Defense' },
    { id: 'n2', label: 'Internal Segmentation' },
  ];
  const treeEdges = interactivePayload.correct_edges || [
    { parent: 'root', child: 'n1' },
    { parent: 'root', child: 'n2' },
  ];

  // Fill Blank defaults
  const fbAnswers = interactivePayload.accepted_answers || ['192.168.1.1'];
  const fbCaseSensitive = Boolean(interactivePayload.case_sensitive);
  const fbRegex = interactivePayload.regex_pattern || '';

  // Live Emulator Interaction State
  const [emulatorInteraction, setEmulatorInteraction] = useState(null);
  const [emulatorSingleSelect, setEmulatorSingleSelect] = useState('');
  const [emulatorMultiSelect, setEmulatorMultiSelect] = useState([]);
  const [emulatorEvaluation, setEmulatorEvaluation] = useState(null);

  // Form submission state
  const [saving, setSaving] = useState(false);
  const [statusMessage, setStatusMessage] = useState(null);
  const [errorMessage, setErrorMessage] = useState(null);

  // Handle Type Change
  const handleTypeChange = (newType) => {
    setItemType(newType);
    setEmulatorInteraction(null);
    setEmulatorSingleSelect('');
    setEmulatorMultiSelect([]);
    setEmulatorEvaluation(null);

    // Set guessing parameter intelligent default
    if (newType === 'single_choice') {
      setGuessing(0.25);
    } else if (newType === 'multiple_choice') {
      setGuessing(0.125);
    } else {
      setGuessing(0.0);
    }
  };

  // Option operations
  const handleAddOption = () => {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const nextKey = letters[options.length] || `OPT${options.length + 1}`;
    setOptions([...options, { key: nextKey, text: `New Option ${nextKey}`, is_correct: false }]);
  };

  const handleRemoveOption = (index) => {
    if (options.length <= 2) return;
    setOptions(options.filter((_, idx) => idx !== index));
  };

  const handleOptionChange = (index, field, value) => {
    const updated = options.map((opt, idx) => {
      if (idx !== index) {
        if (field === 'is_correct' && itemType === 'single_choice' && value) {
          return { ...opt, is_correct: false };
        }
        return opt;
      }
      return { ...opt, [field]: value };
    });
    setOptions(updated);
  };

  // Format toolbar helper
  const insertFormatting = (prefix, suffix = '') => {
    setContent((prev) => `${prev}${prefix}text${suffix}`);
  };

  // Build sanitized question object for live emulator
  const liveQuestion = useMemo(() => {
    let payload = {};

    if (itemType === 'drag_drop') {
      payload = { sources: ddSources, targets: ddTargets };
    } else if (itemType === 'hotspot') {
      payload = { image_src: hsImageSrc, target_shape: hsShape, target_bounds: hsBounds };
    } else if (itemType === 'build_list') {
      payload = { available_items: blItems, correct_sequence: blSequence };
    } else if (itemType === 'cloze') {
      payload = { template: clozeTemplate, blanks: clozeBlanks };
    } else if (itemType === 'create_tree') {
      payload = { root_label: treeRoot, available_nodes: treeNodes, correct_edges: treeEdges };
    } else if (itemType === 'fill_blank') {
      payload = { accepted_answers: fbAnswers, case_sensitive: fbCaseSensitive, regex_pattern: fbRegex };
    }

    return {
      id: 999999,
      type: itemType,
      content: content || 'Enter question stem in the authoring studio on the left...',
      options,
      interactive_payload: payload,
      exhibits,
      domain,
      bloom_level: bloomLevel,
      difficulty,
    };
  }, [
    itemType,
    content,
    options,
    exhibits,
    domain,
    bloomLevel,
    difficulty,
    ddSources,
    ddTargets,
    hsImageSrc,
    hsShape,
    hsBounds,
    blItems,
    blSequence,
    clozeTemplate,
    clozeBlanks,
    treeRoot,
    treeNodes,
    treeEdges,
    fbAnswers,
    fbCaseSensitive,
    fbRegex,
  ]);

  // Test Answer Evaluation in Live Emulator
  const handleTestEvaluation = () => {
    let correct = false;
    let score = 0;
    let message = '';

    if (itemType === 'single_choice') {
      const correctOpt = options.find((o) => o.is_correct);
      correct = emulatorSingleSelect && emulatorSingleSelect === correctOpt?.key;
      score = correct ? 1.0 : 0.0;
      message = correct ? 'Correct single-choice selection!' : 'Incorrect choice.';
    } else if (itemType === 'multiple_choice') {
      const correctKeys = options.filter((o) => o.is_correct).map((o) => o.key).sort();
      const chosen = [...emulatorMultiSelect].sort();
      correct = correctKeys.length > 0 && JSON.stringify(correctKeys) === JSON.stringify(chosen);
      score = correct ? 1.0 : 0.0;
      message = correct ? 'Exact multiple choice match!' : 'Selection does not match correct keys.';
    } else if (itemType === 'drag_drop') {
      const resp = emulatorInteraction || {};
      const matches = ddTargets.filter((t) => resp[t.id] === t.correct_source_id);
      correct = matches.length === ddTargets.length;
      score = ddTargets.length > 0 ? matches.length / ddTargets.length : 0;
      message = `${matches.length} of ${ddTargets.length} slots paired correctly.`;
    } else if (itemType === 'build_list') {
      const resp = emulatorInteraction || [];
      correct = JSON.stringify(resp) === JSON.stringify(blSequence);
      score = correct ? 1.0 : 0.0;
      message = correct ? 'Sequence order is 100% correct!' : 'Sequence order mismatch.';
    } else if (itemType === 'cloze') {
      const resp = emulatorInteraction || {};
      const blankKeys = Object.keys(clozeBlanks);
      const matches = blankKeys.filter((k) => resp[k] === clozeBlanks[k]?.correct_answer);
      correct = matches.length === blankKeys.length;
      score = blankKeys.length > 0 ? matches.length / blankKeys.length : 0;
      message = `${matches.length} of ${blankKeys.length} blanks answered correctly.`;
    } else if (itemType === 'fill_blank') {
      const text = typeof emulatorInteraction === 'string' ? emulatorInteraction.trim() : '';
      correct = fbAnswers.some((ans) => fbCaseSensitive ? text === ans : text.toLowerCase() === ans.toLowerCase());
      score = correct ? 1.0 : 0.0;
      message = correct ? 'Accepted answer verified!' : 'Response does not match accepted answers.';
    } else {
      correct = true;
      score = 1.0;
      message = 'Response verified.';
    }

    setEmulatorEvaluation({ correct, score, message });
  };

  // Save & Publish Question
  const handleSaveQuestion = async () => {
    if (!content.trim()) {
      setErrorMessage('Question stem content cannot be empty.');
      return;
    }
    if (!sessionId) {
      setErrorMessage('Please select a target curriculum session.');
      return;
    }

    setSaving(true);
    setErrorMessage(null);
    setStatusMessage(null);

    const payload = {
      session_id: Number(sessionId),
      type: itemType,
      content,
      domain: domain || 'General Domain',
      bloom_level: bloomLevel,
      difficulty: Number(difficulty),
      discrimination: Number(discrimination),
      guessing: Number(guessing),
      explanation,
      reference,
      options: ['single_choice', 'multiple_choice'].includes(itemType) ? options : [],
      interactive_payload: liveQuestion.interactive_payload,
      exhibits,
      correct_key: itemType === 'single_choice' ? options.find((o) => o.is_correct)?.key : null,
    };

    try {
      if (isEditing) {
        await axios.put(`/admin/questions/${editingQuestion.id}`, payload);
        setStatusMessage('Question updated and published successfully.');
      } else {
        await axios.post('/admin/questions', payload);
        setStatusMessage('New question authored, calibrated, and published successfully.');
      }
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to save question. Please verify fields.');
    } finally {
      setSaving(false);
    }
  };

  // Difficulty verbal label
  const difficultyBadge = useMemo(() => {
    const b = Number(difficulty);
    if (b < -1.5) return { label: 'Elementary / Baseline', color: 'bg-teal-500/15 text-teal-300' };
    if (b < -0.5) return { label: 'Accessible', color: 'bg-brand-500/15 text-brand-300' };
    if (b <= 0.5) return { label: 'Standard Core', color: 'bg-brand-500/20 text-brand-300 font-bold' };
    if (b <= 1.5) return { label: 'Advanced Threshold', color: 'bg-orange-500/15 text-orange-300' };
    return { label: 'Master / Extreme', color: 'bg-red-500/15 text-red-300 font-bold' };
  }, [difficulty]);

  return (
    <DashboardLayout>
      <Head title="Passimark Designer — Visual Authoring Studio" />

      <div className="mx-auto max-w-[1600px] px-4 py-4">
        {/* Top Header Strip */}
        <div className="flex flex-col gap-4 border-b border-slate-800 pb-5 md:flex-row md:items-center md:justify-between">
          <div>
            <div className="flex items-center gap-2">
              <span className="rounded bg-brand-500/15 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider text-brand-300 border border-brand-500/30">
                WYSIWYG Authoring Studio
              </span>
              <span className="text-xs text-slate-400">VCE Designer Superiority Mode</span>
            </div>
            <h1 className="mt-1 text-2xl font-bold text-white flex items-center gap-2">
              <Sparkles className="h-6 w-6 text-brand-400" />
              <span>Passimark Designer</span>
            </h1>
          </div>

          <div className="flex items-center gap-3">
            <Link
              href="/admin/passimark"
              className="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-700 transition"
            >
              Control Center
            </Link>
            <button
              type="button"
              onClick={handleSaveQuestion}
              disabled={saving}
              className="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2 text-xs font-bold text-slate-950 transition hover:bg-brand-400 disabled:opacity-50 shadow-md"
            >
              <Save className="h-4 w-4" />
              <span>{saving ? 'Publishing...' : isEditing ? 'Update Question' : 'Publish Question'}</span>
            </button>
          </div>
        </div>

        {/* Status Alerts */}
        {statusMessage && (
          <div className="mt-4 flex items-center gap-2 rounded-xl border border-brand-500/40 bg-brand-500/15 p-3 text-xs text-brand-300">
            <CheckCircle2 className="h-4 w-4 shrink-0" />
            <span>{statusMessage}</span>
          </div>
        )}
        {errorMessage && (
          <div className="mt-4 flex items-center gap-2 rounded-xl border border-red-500/40 bg-red-500/15 p-3 text-xs text-red-300">
            <AlertTriangle className="h-4 w-4 shrink-0" />
            <span>{errorMessage}</span>
          </div>
        )}

        {/* Split Studio Grid: Authoring on Left, Live Emulator on Right */}
        <div className="mt-6 grid gap-8 lg:grid-cols-12 items-start">
          {/* =========================================================================
           * LEFT COLUMN: AUTHORING STUDIO (Col 7)
           * ======================================================================= */}
          <div className="lg:col-span-7 space-y-6">
            {/* 1. Curriculum & Type Selectors */}
            <section className="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm">
              <h2 className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                1. Assessment Placement & Question Format
              </h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <label className="text-xs font-medium text-slate-300">Target Session / Exam</label>
                  <select
                    value={sessionId}
                    onChange={(e) => setSessionId(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-100 focus:border-brand-500 focus:outline-none"
                  >
                    {sessions.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.certification_track?.title ? `[${s.certification_track.title}] ` : ''}
                        Session {s.number}: {s.title}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="text-xs font-medium text-slate-300">Question Item Type</label>
                  <select
                    value={itemType}
                    onChange={(e) => handleTypeChange(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-brand-500/40 bg-slate-950 px-3 py-2 text-xs font-semibold text-brand-300 focus:border-brand-500 focus:outline-none"
                  >
                    <option value="single_choice">Single Choice (1-of-N)</option>
                    <option value="multiple_choice">Multiple Choice (K-of-N)</option>
                    <option value="drag_drop">Drag &amp; Drop Pairing</option>
                    <option value="hotspot">Point &amp; Click / Hotspot</option>
                    <option value="build_list">Build List &amp; Reorder</option>
                    <option value="cloze">Drop-Down Cloze</option>
                    <option value="create_tree">Create-a-Tree Hierarchy</option>
                    <option value="fill_blank">Fill in the Blank</option>
                    <option value="case_study">Case Study Testlet</option>
                  </select>
                </div>
              </div>
            </section>

            {/* 2. Question Stem WYSIWYG Editor */}
            <section className="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm">
              <div className="flex items-center justify-between mb-2">
                <h2 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                  2. Question Stem (Markdown &amp; Math)
                </h2>
                {/* Format buttons */}
                <div className="flex items-center gap-1">
                  <button
                    type="button"
                    onClick={() => insertFormatting('**', '**')}
                    className="rounded p-1 text-slate-400 hover:bg-slate-800 hover:text-slate-100 text-xs font-bold"
                    title="Bold"
                  >
                    B
                  </button>
                  <button
                    type="button"
                    onClick={() => insertFormatting('*', '*')}
                    className="rounded p-1 text-slate-400 hover:bg-slate-800 hover:text-slate-100 text-xs italic"
                    title="Italic"
                  >
                    I
                  </button>
                  <button
                    type="button"
                    onClick={() => insertFormatting('`', '`')}
                    className="rounded p-1 text-slate-400 hover:bg-slate-800 hover:text-slate-100 text-xs font-mono"
                    title="Code"
                  >
                    &lt;/&gt;
                  </button>
                  <button
                    type="button"
                    onClick={() => insertFormatting('$', '$')}
                    className="rounded p-1 text-slate-400 hover:bg-slate-800 hover:text-brand-300 text-xs font-mono"
                    title="Math Equation ($..$)"
                  >
                    $f(x)$
                  </button>
                </div>
              </div>

              <textarea
                value={content}
                onChange={(e) => setContent(e.target.value)}
                placeholder="Compose the candidate question stem here. Supports markdown syntax and KaTeX equations..."
                rows={4}
                className="w-full rounded-xl border border-slate-700 bg-slate-950 p-3.5 text-sm text-slate-100 placeholder-slate-500 focus:border-brand-500 focus:outline-none font-medium leading-relaxed"
              />
            </section>

            {/* 3. Specialized Interactive Item Designer */}
            <section className="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                  3. Format Designer: {itemType.replace('_', ' ').toUpperCase()}
                </h2>
              </div>

              {/* Single / Multiple Choice Designer */}
              {['single_choice', 'multiple_choice'].includes(itemType) && (
                <div className="space-y-3">
                  <p className="text-xs text-slate-400">
                    Define the available answer choices. Mark which choice(s) are correct.
                  </p>
                  {options.map((opt, idx) => (
                    <div
                      key={opt.key}
                      className="flex items-center gap-3 rounded-lg border border-slate-800 bg-slate-950 p-2.5"
                    >
                      <input
                        type={itemType === 'single_choice' ? 'radio' : 'checkbox'}
                        name="correct_choice"
                        checked={opt.is_correct}
                        onChange={(e) => handleOptionChange(idx, 'is_correct', e.target.checked)}
                        className="h-4 w-4 text-brand-500 focus:ring-brand-500 rounded"
                        title="Mark as correct"
                      />
                      <span className="font-bold text-xs text-brand-300 w-5">{opt.key}.</span>
                      <input
                        type="text"
                        value={opt.text}
                        onChange={(e) => handleOptionChange(idx, 'text', e.target.value)}
                        className="flex-1 rounded-md border border-slate-700 bg-slate-900 px-2.5 py-1 text-xs text-slate-100"
                      />
                      <button
                        type="button"
                        onClick={() => handleRemoveOption(idx)}
                        disabled={options.length <= 2}
                        className="rounded p-1 text-slate-500 hover:text-red-400 disabled:opacity-20"
                        title="Remove option"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  ))}

                  <button
                    type="button"
                    onClick={handleAddOption}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-700 transition"
                  >
                    <Plus className="h-3.5 w-3.5 text-brand-300" /> Add Choice
                  </button>
                </div>
              )}

              {/* Drag & Drop Pairing Designer */}
              {itemType === 'drag_drop' && (
                <div className="space-y-4">
                  <div>
                    <h3 className="text-xs font-semibold text-slate-300 mb-2">Source Tokens (Draggable Cards)</h3>
                    <div className="space-y-2">
                      {ddSources.map((src, idx) => (
                        <div key={src.id} className="flex gap-2">
                          <input
                            type="text"
                            value={src.label}
                            onChange={(e) => {
                              const next = [...ddSources];
                              next[idx].label = e.target.value;
                              setInteractivePayload({ ...interactivePayload, sources: next, targets: ddTargets });
                            }}
                            className="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1 text-xs text-slate-100"
                            placeholder="Card text..."
                          />
                        </div>
                      ))}
                    </div>
                  </div>

                  <div>
                    <h3 className="text-xs font-semibold text-slate-300 mb-2">Target Slots &amp; Correct Match</h3>
                    <div className="space-y-2">
                      {ddTargets.map((tgt, idx) => (
                        <div key={tgt.id} className="flex gap-2 items-center">
                          <input
                            type="text"
                            value={tgt.slot_label}
                            onChange={(e) => {
                              const next = [...ddTargets];
                              next[idx].slot_label = e.target.value;
                              setInteractivePayload({ ...interactivePayload, sources: ddSources, targets: next });
                            }}
                            className="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1 text-xs text-slate-100"
                            placeholder="Target slot label..."
                          />
                          <span className="text-xs text-slate-400">matches:</span>
                          <select
                            value={tgt.correct_source_id}
                            onChange={(e) => {
                              const next = [...ddTargets];
                              next[idx].correct_source_id = e.target.value;
                              setInteractivePayload({ ...interactivePayload, sources: ddSources, targets: next });
                            }}
                            className="rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1 text-xs text-brand-300"
                          >
                            {ddSources.map((s) => (
                              <option key={s.id} value={s.id}>
                                {s.label}
                              </option>
                            ))}
                          </select>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              )}

              {/* Hotspot Builder */}
              {itemType === 'hotspot' && (
                <div className="space-y-3">
                  <div>
                    <label className="text-xs text-slate-300">Exhibit Image URL:</label>
                    <input
                      type="text"
                      value={hsImageSrc}
                      onChange={(e) => {
                        setInteractivePayload({
                          ...interactivePayload,
                          image_src: e.target.value,
                          target_shape: hsShape,
                          target_bounds: hsBounds,
                        });
                      }}
                      className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1 text-xs text-slate-100 font-mono"
                    />
                  </div>

                  <div className="grid grid-cols-3 gap-2">
                    <div>
                      <label className="text-[11px] text-slate-400">Center X (px):</label>
                      <input
                        type="number"
                        value={hsBounds.center_x ?? 256}
                        onChange={(e) => {
                          const bounds = { ...hsBounds, center_x: parseFloat(e.target.value) || 0 };
                          setInteractivePayload({
                            ...interactivePayload,
                            image_src: hsImageSrc,
                            target_shape: hsShape,
                            target_bounds: bounds,
                          });
                        }}
                        className="mt-1 w-full rounded border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-slate-100 font-mono"
                      />
                    </div>
                    <div>
                      <label className="text-[11px] text-slate-400">Center Y (px):</label>
                      <input
                        type="number"
                        value={hsBounds.center_y ?? 256}
                        onChange={(e) => {
                          const bounds = { ...hsBounds, center_y: parseFloat(e.target.value) || 0 };
                          setInteractivePayload({
                            ...interactivePayload,
                            image_src: hsImageSrc,
                            target_shape: hsShape,
                            target_bounds: bounds,
                          });
                        }}
                        className="mt-1 w-full rounded border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-slate-100 font-mono"
                      />
                    </div>
                    <div>
                      <label className="text-[11px] text-slate-400">Tolerance Radius:</label>
                      <input
                        type="number"
                        value={hsBounds.radius ?? 40}
                        onChange={(e) => {
                          const bounds = { ...hsBounds, radius: parseFloat(e.target.value) || 10 };
                          setInteractivePayload({
                            ...interactivePayload,
                            image_src: hsImageSrc,
                            target_shape: hsShape,
                            target_bounds: bounds,
                          });
                        }}
                        className="mt-1 w-full rounded border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-slate-100 font-mono"
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* Build List Designer */}
              {itemType === 'build_list' && (
                <div className="space-y-3">
                  <p className="text-xs text-slate-400">Configure sequence steps and expected order.</p>
                  <div className="space-y-2">
                    {blItems.map((item, idx) => (
                      <div key={item.id} className="flex items-center gap-2">
                        <span className="flex h-5 w-5 items-center justify-center rounded bg-brand-500/20 text-[10px] font-bold text-brand-300">
                          {idx + 1}
                        </span>
                        <input
                          type="text"
                          value={item.text}
                          onChange={(e) => {
                            const next = [...blItems];
                            next[idx].text = e.target.value;
                            setInteractivePayload({ ...interactivePayload, available_items: next, correct_sequence: blSequence });
                          }}
                          className="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1 text-xs text-slate-100"
                        />
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Cloze Designer */}
              {itemType === 'cloze' && (
                <div className="space-y-3">
                  <div>
                    <label className="text-xs text-slate-300">Text Template with Dropdown Tokens:</label>
                    <textarea
                      value={clozeTemplate}
                      onChange={(e) => {
                        setInteractivePayload({ ...interactivePayload, template: e.target.value, blanks: clozeBlanks });
                      }}
                      rows={2}
                      className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-xs text-slate-100 font-mono"
                    />
                  </div>
                  <div className="space-y-2">
                    {Object.entries(clozeBlanks).map(([bId, conf]) => (
                      <div key={bId} className="rounded-lg border border-slate-800 bg-slate-950 p-2.5 text-xs">
                        <span className="font-bold text-brand-300">Token: {`{{${bId}}}`}</span>
                        <div className="mt-1 flex items-center gap-2">
                          <label className="text-slate-400">Correct Answer:</label>
                          <input
                            type="text"
                            value={conf.correct_answer}
                            onChange={(e) => {
                              const next = { ...clozeBlanks, [bId]: { ...conf, correct_answer: e.target.value } };
                              setInteractivePayload({ ...interactivePayload, template: clozeTemplate, blanks: next });
                            }}
                            className="rounded border border-slate-700 bg-slate-900 px-2 py-0.5 text-xs text-slate-100"
                          />
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Fill in the Blank Designer */}
              {itemType === 'fill_blank' && (
                <div className="space-y-3">
                  <div>
                    <label className="text-xs text-slate-300">Accepted Answers (comma-separated):</label>
                    <input
                      type="text"
                      value={fbAnswers.join(', ')}
                      onChange={(e) => {
                        const arr = e.target.value.split(',').map((s) => s.trim()).filter(Boolean);
                        setInteractivePayload({
                          ...interactivePayload,
                          accepted_answers: arr,
                          case_sensitive: fbCaseSensitive,
                          regex_pattern: fbRegex,
                        });
                      }}
                      className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1 text-xs text-slate-100 font-mono"
                    />
                  </div>
                  <div className="flex items-center gap-4 text-xs">
                    <label className="flex items-center gap-1.5 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={fbCaseSensitive}
                        onChange={(e) => {
                          setInteractivePayload({
                            ...interactivePayload,
                            accepted_answers: fbAnswers,
                            case_sensitive: e.target.checked,
                            regex_pattern: fbRegex,
                          });
                        }}
                        className="rounded border-slate-700 text-brand-500"
                      />
                      <span>Case Sensitive</span>
                    </label>
                  </div>
                </div>
              )}
            </section>

            {/* 4. Psychometrics & Calibration Inspector */}
            <section className="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm">
              <div className="flex items-center justify-between mb-3">
                <h2 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                  <Sliders className="h-4 w-4 text-brand-300" />
                  <span>4. Psychometric IRT 3PL Calibration Inspector</span>
                </h2>
                <span className={`rounded px-2 py-0.5 text-[11px] font-semibold ${difficultyBadge.color}`}>
                  {difficultyBadge.label}
                </span>
              </div>

              <div className="grid gap-4 sm:grid-cols-3">
                {/* Difficulty b */}
                <div>
                  <div className="flex justify-between text-xs text-slate-300 mb-1">
                    <span>Difficulty (b):</span>
                    <span className="font-mono font-bold text-brand-300">{Number(difficulty).toFixed(2)}</span>
                  </div>
                  <input
                    type="range"
                    min="-3.0"
                    max="3.0"
                    step="0.05"
                    value={difficulty}
                    onChange={(e) => setDifficulty(parseFloat(e.target.value))}
                    className="w-full accent-brand-500"
                  />
                  <span className="text-[10px] text-slate-500">Range: -3.0 to +3.0</span>
                </div>

                {/* Discrimination a */}
                <div>
                  <div className="flex justify-between text-xs text-slate-300 mb-1">
                    <span>Discrimination (a):</span>
                    <span className="font-mono font-bold text-brand-300">{Number(discrimination).toFixed(2)}</span>
                  </div>
                  <input
                    type="range"
                    min="0.2"
                    max="2.5"
                    step="0.05"
                    value={discrimination}
                    onChange={(e) => setDiscrimination(parseFloat(e.target.value))}
                    className="w-full accent-brand-500"
                  />
                  <span className="text-[10px] text-slate-500">Fisher Info Slope</span>
                </div>

                {/* Guessing c */}
                <div>
                  <div className="flex justify-between text-xs text-slate-300 mb-1">
                    <span>Pseudo-Guessing (c):</span>
                    <span className="font-mono font-bold text-brand-300">{Number(guessing).toFixed(2)}</span>
                  </div>
                  <input
                    type="range"
                    min="0.0"
                    max="0.5"
                    step="0.01"
                    value={guessing}
                    onChange={(e) => setGuessing(parseFloat(e.target.value))}
                    className="w-full accent-brand-500"
                  />
                  <span className="text-[10px] text-slate-500">0.25 (MC) / 0.0 (Perf)</span>
                </div>
              </div>

              <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                  <label className="text-xs text-slate-400">Bloom's Cognitive Taxonomy:</label>
                  <select
                    value={bloomLevel}
                    onChange={(e) => setBloomLevel(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1.5 text-xs text-slate-100"
                  >
                    {['Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate', 'Create'].map((b) => (
                      <option key={b} value={b}>
                        {b}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="text-xs text-slate-400">Domain / Syllabus Objective:</label>
                  <input
                    type="text"
                    value={domain}
                    onChange={(e) => setDomain(e.target.value)}
                    placeholder="e.g. Identity and Access Management"
                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1.5 text-xs text-slate-100"
                  />
                </div>
              </div>

              <div className="mt-4">
                <label className="text-xs text-slate-400">Fact-Pinned Verifiable Reference Citation:</label>
                <input
                  type="text"
                  value={reference}
                  onChange={(e) => setReference(e.target.value)}
                  placeholder="e.g. NIST SP 800-53 Rev 5 §AC-2, RFC 4861"
                  className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1.5 text-xs text-slate-100 font-mono"
                />
              </div>

              <div className="mt-4">
                <label className="text-xs text-slate-400">Pedagogical Explanation &amp; Distractor Debunking:</label>
                <textarea
                  value={explanation}
                  onChange={(e) => setExplanation(e.target.value)}
                  placeholder="State why the correct answer is right and why each distractor is wrong..."
                  rows={2}
                  className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5 text-xs text-slate-100"
                />
              </div>
            </section>
          </div>

          {/* =========================================================================
           * RIGHT COLUMN: LIVE CANDIDATE EMULATOR (Col 5)
           * ======================================================================= */}
          <div className="lg:col-span-5 sticky top-6 space-y-4">
            <section className="rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-xl">
              {/* Emulator Top Bar */}
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <div className="flex items-center gap-2">
                  <Eye className="h-4 w-4 text-brand-300" />
                  <span className="text-xs font-bold uppercase tracking-wider text-slate-200">
                    Live Candidate Emulator
                  </span>
                </div>
                <span className="rounded bg-brand-500/15 border border-brand-500/30 px-2 py-0.5 text-[10px] font-semibold text-brand-300 animate-pulse">
                  Interactive Preview
                </span>
              </div>

              {/* Item Content Preview */}
              <div className="mt-4">
                <div className="rounded-lg bg-slate-950/80 p-3 text-xs text-slate-400 border border-slate-800">
                  <span className="font-semibold text-slate-200">Question Item Preview</span>
                </div>

                <div className="mt-4 text-sm font-semibold leading-relaxed text-slate-100">
                  {liveQuestion.content}
                </div>

                {/* Exhibits Display if present */}
                {exhibits.length > 0 && (
                  <div className="mt-4">
                    <CaseStudyViewer exhibits={exhibits} />
                  </div>
                )}

                {/* Polymorphic Interactive Preview Renderer */}
                <div className="mt-6">
                  {itemType === 'single_choice' && (
                    <QuestionSingleChoice
                      options={options}
                      selected={emulatorSingleSelect}
                      onChange={setEmulatorSingleSelect}
                    />
                  )}
                  {itemType === 'multiple_choice' && (
                    <QuestionMultiSelect
                      options={options}
                      selections={emulatorMultiSelect}
                      onChange={setEmulatorMultiSelect}
                    />
                  )}
                  {itemType === 'drag_drop' && (
                    <QuestionDragDrop
                      payload={liveQuestion.interactive_payload}
                      value={emulatorInteraction || {}}
                      onChange={setEmulatorInteraction}
                    />
                  )}
                  {itemType === 'hotspot' && (
                    <QuestionHotspot
                      payload={liveQuestion.interactive_payload}
                      value={emulatorInteraction}
                      onChange={setEmulatorInteraction}
                    />
                  )}
                  {itemType === 'build_list' && (
                    <QuestionReorder
                      payload={liveQuestion.interactive_payload}
                      value={emulatorInteraction || []}
                      onChange={setEmulatorInteraction}
                    />
                  )}
                  {itemType === 'cloze' && (
                    <QuestionCloze
                      payload={liveQuestion.interactive_payload}
                      value={emulatorInteraction || {}}
                      onChange={setEmulatorInteraction}
                    />
                  )}
                  {itemType === 'create_tree' && (
                    <QuestionCreateTree
                      payload={liveQuestion.interactive_payload}
                      value={emulatorInteraction || []}
                      onChange={setEmulatorInteraction}
                    />
                  )}
                  {itemType === 'fill_blank' && (
                    <QuestionFillBlank
                      payload={liveQuestion.interactive_payload}
                      value={emulatorInteraction || ''}
                      onChange={setEmulatorInteraction}
                    />
                  )}
                </div>

                {/* Emulator Action & Testing Strip */}
                <div className="mt-6 border-t border-slate-800 pt-4 space-y-3">
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={handleTestEvaluation}
                      className="flex-1 rounded-lg bg-brand-500/15 border border-brand-500/40 py-2 text-xs font-semibold text-brand-300 hover:bg-brand-500/25 transition"
                    >
                      Test Candidate Evaluation
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setEmulatorInteraction(null);
                        setEmulatorSingleSelect('');
                        setEmulatorMultiSelect([]);
                        setEmulatorEvaluation(null);
                      }}
                      className="rounded-lg border border-slate-700 bg-slate-800 p-2 text-slate-400 hover:text-slate-100 transition"
                      title="Reset Emulator"
                    >
                      <RotateCcw className="h-3.5 w-3.5" />
                    </button>
                  </div>

                  {emulatorEvaluation && (
                    <div
                      className={`rounded-lg p-2.5 text-xs flex items-center gap-2 ${
                        emulatorEvaluation.correct
                          ? 'bg-brand-500/15 text-brand-300 border border-brand-500/30'
                          : 'bg-orange-500/15 text-orange-300 border border-orange-500/30'
                      }`}
                    >
                      <CheckCircle2 className="h-4 w-4 shrink-0" />
                      <span>{emulatorEvaluation.message}</span>
                    </div>
                  )}
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
