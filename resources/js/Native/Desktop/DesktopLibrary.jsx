import React, { useState } from 'react';
import { 
  FolderOpen, Play, CheckCircle2, Clock, Brain, UploadCloud, 
  Shield, Layers, ChevronRight, FileCode, Award 
} from 'lucide-react';
import { BUNDLED_EXAMS } from '../Data/bundledExams';

export default function DesktopLibrary({ onSelectExam, onFileLoad, onOpenPasteModal }) {
  const [selectedExamId, setSelectedExamId] = useState(BUNDLED_EXAMS[0].id);
  const [examMode, setExamMode] = useState('practice'); // practice, timed, cat
  const [dragActive, setDragActive] = useState(false);

  const selectedExam = BUNDLED_EXAMS.find((e) => e.id === selectedExamId) || BUNDLED_EXAMS[0];

  const handleDrag = (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true);
    } else if (e.type === 'dragleave') {
      setDragActive(false);
    }
  };

  const handleDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      const file = e.dataTransfer.files[0];
      onFileLoad(file);
    }
  };

  const handleFileInput = (e) => {
    if (e.target.files && e.target.files[0]) {
      onFileLoad(e.target.files[0]);
    }
  };

  return (
    <div className="flex flex-1 flex-col overflow-y-auto bg-slate-950 p-6 text-slate-100">
      {/* Brand Header Banner */}
      <div className="flex items-center justify-between border-b border-slate-800/80 pb-5 mb-6">
        <div className="flex items-center gap-4">
          <img 
            src="./images/passimark/passimark_original_transparent.png" 
            alt="Passimark Logo" 
            className="h-12 w-auto object-contain drop-shadow"
            onError={(e) => { e.currentTarget.src = './images/passimark/icon.png'; }}
          />
          <div>
            <div className="flex items-center gap-2.5">
              <h1 className="text-xl font-extrabold tracking-tight text-slate-100">
                Passimark Certification Suite
              </h1>
              <span className="rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-bold text-emerald-400 border border-emerald-500/20">
                v2.0 VCE Superiority
              </span>
            </div>
            <p className="text-xs text-slate-400 mt-0.5">
              Psychometrically Grounded IRT 3PL Simulator • Native Multi-Window Desktop Engine • Hexadigitall Inc.
            </p>
          </div>
        </div>
        <div className="hidden sm:flex items-center gap-2">
          <span className="rounded-lg bg-slate-900 border border-slate-800 px-3 py-1.5 text-xs text-slate-400 font-mono">
            Tauri 2.0 Rust Core
          </span>
        </div>
      </div>

      {/* Drag & Drop Ingestion Container */}
      <div 
        onDragEnter={handleDrag}
        onDragOver={handleDrag}
        onDragLeave={handleDrag}
        onDrop={handleDrop}
        className={`relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-all ${
          dragActive 
            ? 'border-brand-400 bg-brand-500/10' 
            : 'border-slate-800 bg-slate-900/60 hover:border-slate-700'
        }`}
      >
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-800 text-brand-400 mb-3 shadow-inner">
          <UploadCloud className="h-6 w-6" />
        </div>
        <h2 className="text-base font-semibold text-slate-100">
          Open Certification Container or Legacy VCE File
        </h2>
        <p className="mt-1 text-xs text-slate-400 max-w-md">
          Drag and drop your <span className="text-brand-400 font-mono">.psmk</span> (Passimark), <span className="text-blue-400 font-mono">.vce</span> (Visual CertExam), or <span className="text-purple-400 font-mono">.psme</span> (Encrypted) package anywhere into this window.
        </p>

        <div className="mt-4 flex flex-wrap items-center justify-center gap-3">
          <label className="cursor-pointer rounded-lg bg-slate-800 px-4 py-2 text-xs font-medium text-slate-200 hover:bg-slate-700 hover:text-white transition-colors border border-slate-700 flex items-center gap-2">
            <FolderOpen className="h-4 w-4 text-brand-400" />
            <span>Browse Local File (.vce, .psmk, .txt)</span>
            <input 
              type="file" 
              accept=".psmk,.vce,.psme,.json,.txt,.csv,.md" 
              className="hidden" 
              onChange={handleFileInput}
            />
          </label>
          <button
            type="button"
            onClick={onOpenPasteModal}
            className="rounded-lg bg-slate-800/90 px-4 py-2 text-xs font-medium text-emerald-300 hover:bg-slate-700 hover:text-emerald-200 transition-colors border border-slate-700 flex items-center gap-2"
          >
            <FileCode className="h-4 w-4 text-emerald-400" />
            <span>Paste Exam Dump Text</span>
          </button>
        </div>
      </div>

      {/* Main Content: Bundled Certification Library */}
      <div className="mt-8 flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-sm font-semibold tracking-wide text-slate-200 uppercase">
              Installed Native Certification Packages
            </h3>
            <p className="text-xs text-slate-400">
              Select an authored standard exam bundle to launch full adaptive or timed simulation immediately.
            </p>
          </div>
          <span className="rounded bg-slate-800 px-2.5 py-1 text-xs text-slate-300 font-medium">
            {BUNDLED_EXAMS.length} Bundled Exams Available
          </span>
        </div>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* Exam List Column */}
          <div className="flex flex-col gap-3 lg:col-span-2">
            {BUNDLED_EXAMS.map((exam) => {
              const isSelected = exam.id === selectedExamId;
              return (
                <div
                  key={exam.id}
                  onClick={() => setSelectedExamId(exam.id)}
                  className={`group flex cursor-pointer items-start justify-between rounded-xl border p-4 transition-all ${
                    isSelected
                      ? 'border-brand-500 bg-slate-900/90 shadow-md ring-1 ring-brand-500/20'
                      : 'border-slate-800/80 bg-slate-900/40 hover:border-slate-700 hover:bg-slate-900/70'
                  }`}
                >
                  <div className="flex items-start gap-3.5">
                    <div className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg font-bold text-xs ${
                      isSelected ? 'bg-brand-500 text-slate-950' : 'bg-slate-800 text-slate-400 group-hover:text-slate-200'
                    }`}>
                      {exam.code.slice(0, 3)}
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-slate-100 text-sm">{exam.title}</span>
                      </div>
                      <p className="mt-1 text-xs text-slate-400 line-clamp-2 leading-relaxed">
                        {exam.description}
                      </p>
                      <div className="mt-2.5 flex items-center gap-4 text-[11px] text-slate-500">
                        <span className="flex items-center gap-1">
                          <Layers className="h-3 w-3 text-slate-400" />
                          {exam.questions.length} Items
                        </span>
                        <span className="flex items-center gap-1">
                          <Clock className="h-3 w-3 text-slate-400" />
                          {exam.timeLimitMinutes} Mins
                        </span>
                        <span className="flex items-center gap-1">
                          <Award className="h-3 w-3 text-brand-400" />
                          Pass: {exam.passingScore}
                        </span>
                      </div>
                    </div>
                  </div>
                  <ChevronRight className={`h-5 w-5 shrink-0 self-center transition-transform ${
                    isSelected ? 'text-brand-400 translate-x-0.5' : 'text-slate-600'
                  }`} />
                </div>
              );
            })}
          </div>

          {/* Configuration & Launch Panel */}
          <div className="flex flex-col rounded-xl border border-slate-800 bg-slate-900/80 p-5">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
              Simulation Configuration
            </h4>

            <div className="flex flex-col gap-4">
              <div>
                <span className="text-xs font-medium text-slate-300">Selected Package</span>
                <p className="text-sm font-semibold text-brand-400 mt-0.5">{selectedExam.title}</p>
                <p className="text-[11px] text-slate-400 mt-1">{selectedExam.category}</p>
              </div>

              <div className="border-t border-slate-800 pt-3">
                <span className="text-xs font-medium text-slate-300">Select Mode</span>
                <div className="mt-2 flex flex-col gap-2">
                  <label 
                    onClick={() => setExamMode('practice')}
                    className={`flex cursor-pointer items-start gap-2.5 rounded-lg border p-2.5 transition-colors ${
                      examMode === 'practice' 
                        ? 'border-brand-500 bg-brand-500/10 text-slate-100' 
                        : 'border-slate-800 bg-slate-950/60 text-slate-400 hover:border-slate-700'
                    }`}
                  >
                    <CheckCircle2 className={`h-4 w-4 shrink-0 mt-0.5 ${examMode === 'practice' ? 'text-brand-400' : 'text-slate-600'}`} />
                    <div>
                      <div className="text-xs font-semibold text-slate-200">Practice Mode</div>
                      <div className="text-[10px] text-slate-400">Immediate per-item explanations, AI distractor reasoning, no countdown.</div>
                    </div>
                  </label>

                  <label 
                    onClick={() => setExamMode('timed')}
                    className={`flex cursor-pointer items-start gap-2.5 rounded-lg border p-2.5 transition-colors ${
                      examMode === 'timed' 
                        ? 'border-blue-500 bg-blue-500/10 text-slate-100' 
                        : 'border-slate-800 bg-slate-950/60 text-slate-400 hover:border-slate-700'
                    }`}
                  >
                    <Clock className={`h-4 w-4 shrink-0 mt-0.5 ${examMode === 'timed' ? 'text-blue-400' : 'text-slate-600'}`} />
                    <div>
                      <div className="text-xs font-semibold text-slate-200">Timed Exam Mode</div>
                      <div className="text-[10px] text-slate-400">Strict countdown timer, zero answer leakage until submission.</div>
                    </div>
                  </label>

                  <label 
                    onClick={() => setExamMode('cat')}
                    className={`flex cursor-pointer items-start gap-2.5 rounded-lg border p-2.5 transition-colors ${
                      examMode === 'cat' 
                        ? 'border-purple-500 bg-purple-500/10 text-slate-100' 
                        : 'border-slate-800 bg-slate-950/60 text-slate-400 hover:border-slate-700'
                    }`}
                  >
                    <Brain className={`h-4 w-4 shrink-0 mt-0.5 ${examMode === 'cat' ? 'text-purple-400' : 'text-slate-600'}`} />
                    <div>
                      <div className="text-xs font-semibold text-slate-200">Adaptive CAT (IRT 3PL)</div>
                      <div className="text-[10px] text-slate-400">Fisher information routing, dynamic difficulty adjustment.</div>
                    </div>
                  </label>
                </div>
              </div>

              <div className="mt-2 border-t border-slate-800 pt-4">
                <button
                  onClick={() => onSelectExam(selectedExam, examMode)}
                  className="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 py-2.5 text-xs font-bold text-slate-950 shadow-lg shadow-brand-500/20 hover:bg-brand-400 transition-all active:scale-[0.99]"
                >
                  <Play className="h-4 w-4 fill-slate-950" />
                  <span>Start Simulation ({selectedExam.questions.length} Items)</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
