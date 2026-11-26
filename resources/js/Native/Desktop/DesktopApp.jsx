import React, { useState, useEffect } from 'react';
import DesktopToolbar from './DesktopToolbar';
import DesktopLibrary from './DesktopLibrary';
import DesktopPlayer from './DesktopPlayer';
import DesktopDesigner from './DesktopDesigner';
import DesktopShortcutsModal from '../../Components/Chrome/DesktopShortcutsModal';
import { TauriBridge } from '../../Services/Desktop/TauriBridge';
import { PackageParser } from '../../Services/Desktop/PackageParser';
import { BUNDLED_EXAMS } from '../Data/bundledExams';
import { AlertTriangle, FileText, Play, CheckCircle2, X } from 'lucide-react';

export default function DesktopApp() {
  const [activeMode, setActiveMode] = useState('player'); // 'player' | 'designer'
  const [activeExam, setActiveExam] = useState(null);
  const [examMode, setExamMode] = useState('practice');
  const [showLibrary, setShowLibrary] = useState(true);
  const [showShortcuts, setShowShortcuts] = useState(false);
  const [showCalculator, setShowCalculator] = useState(false);
  const [calcInput, setCalcInput] = useState('');
  const [calcResult, setCalcResult] = useState('');
  const [importStatus, setImportStatus] = useState(null);
  const [vceNotice, setVceNotice] = useState(null);
  const [showPasteModal, setShowPasteModal] = useState(false);
  const [pasteContent, setPasteContent] = useState('');

  // Handle Tauri OS file association launch
  useEffect(() => {
    if (window.__TAURI__?.event?.listen) {
      const unlistenPromise = window.__TAURI__.event.listen('open-package-file', (event) => {
        const filePath = event.payload?.path;
        if (filePath) {
          handleLoadPackagePath(filePath);
        }
      });
      return () => {
        unlistenPromise.then((fn) => fn());
      };
    }
  }, []);

  const handleLoadPackagePath = async (filePath) => {
    try {
      setImportStatus('Parsing file with native desktop core...');
      if (window.__TAURI__?.core?.invoke) {
        const parsed = await window.__TAURI__.core.invoke('parse_package_file', { filePath });
        if (parsed?.error === 'ENCRYPTED_VCE') {
          setVceNotice(parsed);
          setImportStatus(null);
          return;
        }
        if (parsed && parsed.questions?.length > 0) {
          setActiveExam(parsed);
          setExamMode('practice');
          setShowLibrary(false);
          setImportStatus(null);
          return;
        }
      }
    } catch (err) {
      console.warn('Native parser fallback:', err);
    }
    setImportStatus(null);
  };

  const handleSelectExam = (exam, selectedMode) => {
    setActiveExam(exam);
    setExamMode(selectedMode);
    setShowLibrary(false);
  };

  const handleFileLoad = async (file) => {
    try {
      setImportStatus(`Parsing ${file.name}...`);
      const parsedExam = await PackageParser.parseFile(file);
      if (parsedExam?.error === 'ENCRYPTED_VCE') {
        setVceNotice(parsedExam);
        return;
      }
      if (parsedExam && parsedExam.questions?.length > 0) {
        setActiveExam(parsedExam);
        setExamMode('practice');
        setShowLibrary(false);
      } else {
        alert(`No questions could be extracted from ${file.name}. Please ensure this is a valid .vce, .psmk, .json, or .txt file.`);
      }
    } catch (err) {
      console.error('Failed to parse package:', err);
      alert(`Error reading ${file.name}: ${err.message}`);
    } finally {
      setImportStatus(null);
    }
  };

  const handleImportPastedText = () => {
    if (!pasteContent.trim()) return;
    try {
      const parsed = PackageParser.parseText(pasteContent, 'Pasted Exam Bank');
      if (parsed && parsed.questions?.length > 0) {
        setActiveExam(parsed);
        setExamMode('practice');
        setShowLibrary(false);
        setShowPasteModal(false);
        setPasteContent('');
      } else {
        alert('Could not detect valid questions. Format questions like:\nQuestion 1:\nText...\nA. Choice 1\nB. Choice 2\nAnswer: A');
      }
    } catch (e) {
      alert('Error parsing text: ' + e.message);
    }
  };

  const loadSampleQuestionsIntoPaste = () => {
    setPasteContent(`Question 1:
What is the primary difference between ISO 27001 and ISO 27002?
A. ISO 27001 is auditable certification specification; ISO 27002 is operational guidelines
B. ISO 27001 is deprecated; ISO 27002 is active
C. ISO 27001 is for cloud only; ISO 27002 is on-premises
D. ISO 27001 is a US federal regulation; ISO 27002 is European
Answer: A
Explanation: ISO 27001 specifies the baseline management requirements for an ISMS, whereas ISO 27002 provides recommended code of practice controls.

Question 2:
Which arm of the CIA triad is violated when an attacker unauthorizedly modifies database records?
A. Confidentiality
B. Authenticity
C. Integrity
D. Availability
Answer: C
Explanation: Unauthorized modification of data violates Integrity.`);
  };

  const handleCalculatorEval = () => {
    try {
      const sanitized = calcInput.replace(/[^0-9+\-*/().]/g, '');
      const res = Function(`'use strict'; return (${sanitized})`)();
      setCalcResult(String(res));
    } catch (err) {
      setCalcResult('Error');
    }
  };

  return (
    <div className="flex h-screen flex-col overflow-hidden bg-slate-950 text-slate-100 font-sans antialiased">
      {/* Native Desktop Menu Bar & Toolbar */}
      <DesktopToolbar
        activeMode={activeMode}
        onModeChange={(m) => setActiveMode(m)}
        activeExam={activeExam}
        onOpenLibrary={() => setShowLibrary(true)}
        onOpenFile={() => {
          const input = document.createElement('input');
          input.type = 'file';
          input.accept = '.psmk,.vce,.psme,.json,.txt,.csv,.md';
          input.onchange = (e) => e.target.files[0] && handleFileLoad(e.target.files[0]);
          input.click();
        }}
        onOpenCalculator={() => setShowCalculator((c) => !c)}
        onOpenScratchpad={() => TauriBridge.detachScratchpad('Candidate Scratchpad Notes')}
        onOpenShortcuts={() => setShowShortcuts(true)}
        onResetExam={() => {
          setActiveExam(null);
          setShowLibrary(true);
        }}
      />

      {/* Main Workspace: Designer vs Player */}
      <main className="flex flex-1 overflow-hidden">
        {activeMode === 'designer' ? (
          <DesktopDesigner />
        ) : showLibrary || !activeExam ? (
          <DesktopLibrary
            onSelectExam={handleSelectExam}
            onFileLoad={handleFileLoad}
            onOpenPasteModal={() => setShowPasteModal(true)}
          />
        ) : (
          <DesktopPlayer
            exam={activeExam}
            mode={examMode}
            onExitToLibrary={() => setShowLibrary(true)}
          />
        )}
      </main>

      {/* Native Desktop Status Bar */}
      <footer className="flex h-6 shrink-0 items-center justify-between border-t border-slate-900 bg-slate-950 px-3 text-[10px] text-slate-500 font-mono select-none">
        <div className="flex items-center gap-4">
          <span className="flex items-center gap-1.5">
            <span className="h-1.5 w-1.5 rounded-full bg-brand-400" />
            <span className="text-slate-400">Desktop Engine: Tauri 2.0 (Rust MSVC)</span>
          </span>
          <span>RAM Target: &lt; 45 MB</span>
          <span>Cold Boot: &lt; 500 ms</span>
        </div>
        <div className="flex items-center gap-3">
          <span>Multi-Window: Active</span>
          <span>File Assoc: .psmk, .vce, .psme, .txt</span>
        </div>
      </footer>

      {/* Encrypted VCE Guidance Modal */}
      {vceNotice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
          <div className="w-full max-w-lg rounded-2xl border border-amber-500/40 bg-slate-900 p-6 shadow-2xl">
            <div className="flex items-start gap-4">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
                <AlertTriangle className="h-6 w-6" />
              </div>
              <div className="flex-1">
                <div className="flex items-center justify-between">
                  <h3 className="text-base font-bold text-slate-100">
                    Avanset Proprietary VCE Encryption Detected
                  </h3>
                  <button 
                    onClick={() => setVceNotice(null)}
                    className="text-slate-400 hover:text-white"
                  >
                    <X className="h-5 w-5" />
                  </button>
                </div>
                <p className="mt-1 text-xs font-mono text-amber-300">
                  File: {vceNotice.filename || 'VCE Exam'}
                </p>
                <p className="mt-3 text-xs text-slate-300 leading-relaxed">
                  Modern <span className="font-mono text-blue-400">.vce</span> files generated by Avanset Visual CertExam Suite are secured with proprietary AES / SQLCipher encryption to enforce closed software lock-in.
                </p>

                <div className="mt-4 rounded-xl border border-slate-800 bg-slate-950/60 p-3.5 text-xs text-slate-300 space-y-2">
                  <p className="font-semibold text-brand-400">How to practice this exam in Passimark:</p>
                  <ol className="list-decimal list-inside space-y-1.5 text-[11px] text-slate-400">
                    <li>In Visual CertExam Designer, choose <span className="text-slate-200 font-semibold">File → Export → Text (*.txt)</span> or PDF.</li>
                    <li>Drag that text file directly into Passimark to parse all questions, choices, and explanations.</li>
                    <li>Or click <span className="text-emerald-400 font-semibold">"Paste Exam Text"</span> below to paste question text directly!</li>
                  </ol>
                </div>

                <div className="mt-5 flex flex-wrap items-center justify-end gap-2.5">
                  <button
                    onClick={() => {
                      setVceNotice(null);
                      setShowPasteModal(true);
                    }}
                    className="rounded-lg bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-3.5 py-2 text-xs font-semibold hover:bg-emerald-500/30 transition-colors flex items-center gap-1.5"
                  >
                    <FileText className="h-4 w-4" />
                    <span>Paste Exam Text</span>
                  </button>

                  <button
                    onClick={() => {
                      setVceNotice(null);
                      const cissp = BUNDLED_EXAMS.find((e) => e.code === 'CISSP-CAT') || BUNDLED_EXAMS[0];
                      handleSelectExam(cissp, 'cat');
                    }}
                    className="rounded-lg bg-brand-500 px-3.5 py-2 text-xs font-bold text-slate-950 hover:bg-brand-400 transition-colors flex items-center gap-1.5"
                  >
                    <Play className="h-4 w-4 fill-slate-950" />
                    <span>Launch CISSP Simulation (25 Qs)</span>
                  </button>

                  <button
                    onClick={() => setVceNotice(null)}
                    className="rounded-lg bg-slate-800 px-3 py-2 text-xs text-slate-400 hover:text-white transition-colors"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Paste Exam Dump Text Modal */}
      {showPasteModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
          <div className="w-full max-w-2xl rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl flex flex-col max-h-[85vh]">
            <div className="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
              <div className="flex items-center gap-2.5">
                <FileText className="h-5 w-5 text-emerald-400" />
                <h3 className="text-base font-bold text-slate-100">
                  Paste Exam Questions (VCE Text Export or Dumps)
                </h3>
              </div>
              <button 
                onClick={() => setShowPasteModal(false)}
                className="text-slate-400 hover:text-white"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <p className="text-xs text-slate-400 mb-2">
              Paste question text from your study notes, textbook, or VCE text export. Passimark automatically parses question stems, choices A–H, answers, and explanations.
            </p>

            <textarea
              value={pasteContent}
              onChange={(e) => setPasteContent(e.target.value)}
              placeholder="Question 1:
What is separation of duties?
A. Dividing high-risk tasks between individuals
B. Combining roles to save cost
Answer: A
Explanation: Separation of duties prevents fraud through dual oversight."
              className="flex-1 w-full min-h-[260px] rounded-xl bg-slate-950 p-4 text-xs font-mono text-slate-200 border border-slate-800 focus:border-brand-500 focus:outline-none resize-none leading-relaxed"
            />

            <div className="mt-4 flex items-center justify-between border-t border-slate-800 pt-3">
              <button
                type="button"
                onClick={loadSampleQuestionsIntoPaste}
                className="text-xs text-brand-400 hover:underline"
              >
                + Load Sample CISSP Questions
              </button>

              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setShowPasteModal(false)}
                  className="rounded-lg bg-slate-800 px-4 py-2 text-xs text-slate-300 hover:bg-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={handleImportPastedText}
                  className="rounded-lg bg-brand-500 px-5 py-2 text-xs font-bold text-slate-950 hover:bg-brand-400 shadow-md flex items-center gap-1.5"
                >
                  <Play className="h-4 w-4 fill-slate-950" />
                  <span>Parse & Launch Simulation</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Floating Calculator Modal */}
      {showCalculator && (
        <div className="fixed top-14 right-10 w-72 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl p-4 z-50">
          <div className="flex items-center justify-between text-xs text-slate-300 mb-2">
            <span className="font-semibold">Subnet & Math Calculator</span>
            <button onClick={() => setShowCalculator(false)} className="text-slate-500 hover:text-white">✕</button>
          </div>
          <input
            type="text"
            value={calcInput}
            onChange={(e) => setCalcInput(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && handleCalculatorEval()}
            placeholder="e.g. 256 - 64, 2^8"
            className="w-full rounded bg-slate-950 p-2 text-xs font-mono text-slate-100 border border-slate-800"
          />
          <div className="mt-2 flex items-center justify-between">
            <button
              onClick={handleCalculatorEval}
              className="rounded bg-brand-500 px-3 py-1 text-xs font-bold text-slate-950 hover:bg-brand-400"
            >
              Calculate
            </button>
            <span className="text-xs font-mono font-bold text-brand-400">{calcResult}</span>
          </div>
        </div>
      )}

      {/* Keyboard Shortcuts Reference Modal */}
      <DesktopShortcutsModal
        isOpen={showShortcuts}
        onClose={() => setShowShortcuts(false)}
      />
    </div>
  );
}
