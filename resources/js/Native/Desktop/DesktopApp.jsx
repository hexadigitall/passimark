import React, { useState, useEffect } from 'react';
import DesktopToolbar from './DesktopToolbar';
import DesktopLibrary from './DesktopLibrary';
import DesktopPlayer from './DesktopPlayer';
import DesktopDesigner from './DesktopDesigner';
import DesktopShortcutsModal from '../../Components/Chrome/DesktopShortcutsModal';
import { TauriBridge } from '../../Services/Desktop/TauriBridge';
import { PackageParser } from '../../Services/Desktop/PackageParser';

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
      if (parsedExam && parsedExam.questions?.length > 0) {
        setActiveExam(parsedExam);
        setExamMode('practice');
        setShowLibrary(false);
      } else {
        alert(`No questions could be extracted from ${file.name}. Please ensure this is a valid .vce, .psmk, or .json file.`);
      }
    } catch (err) {
      console.error('Failed to parse package:', err);
      alert(`Error reading ${file.name}: ${err.message}`);
    } finally {
      setImportStatus(null);
    }
  };

  const handleCalculatorEval = () => {
    try {
      // Basic math safe evaluator
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
          input.accept = '.psmk,.vce,.psme,.json';
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
          <span>File Assoc: .psmk, .vce, .psme</span>
        </div>
      </footer>

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
