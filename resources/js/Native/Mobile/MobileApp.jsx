import React, { useState, useEffect } from 'react';
import { 
  Layers, Clock, Brain, RefreshCw, CheckCircle2, ChevronRight, 
  Sparkles, Shield, Award, Settings, Zap 
} from 'lucide-react';
import { BUNDLED_EXAMS } from '../Data/bundledExams';
import MobilePlayer from './MobilePlayer';
import { HapticFeedback } from '../../Services/Mobile/HapticFeedback';

export default function MobileApp() {
  const [activeTab, setActiveTab] = useState('library'); // 'library' | 'drill' | 'sync'
  const [activeExam, setActiveExam] = useState(null);
  const [selectedMode, setSelectedMode] = useState('practice');
  const [syncStatus, setSyncStatus] = useState('Offline Storage Active');

  const handleStartExam = (exam, mode) => {
    HapticFeedback.selection();
    setActiveExam(exam);
    setSelectedMode(mode);
  };

  if (activeExam) {
    return (
      <MobilePlayer
        exam={activeExam}
        mode={selectedMode}
        onExitToLibrary={() => setActiveExam(null)}
      />
    );
  }

  return (
    <div 
      className="flex h-screen flex-col overflow-hidden bg-slate-950 text-slate-100 font-sans select-none"
      style={{
        paddingTop: 'env(safe-area-inset-top, 0px)',
      }}
    >
      {/* Mobile App Header */}
      <header className="flex h-14 shrink-0 items-center justify-between border-b border-slate-900 bg-slate-950 px-4">
        <div className="flex items-center gap-2.5">
          <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-500 font-bold text-slate-950 text-xs shadow-md shadow-brand-500/20">
            PM
          </div>
          <div>
            <h1 className="text-sm font-bold text-slate-100 leading-tight">Passimark Mobile</h1>
            <p className="text-[10px] text-brand-400 font-mono">Native SQLite Engine</p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <span className="flex items-center gap-1 rounded-full bg-slate-900 border border-slate-800 px-2 py-0.5 text-[10px] text-slate-400">
            <span className="h-1.5 w-1.5 rounded-full bg-brand-400" />
            <span>SQLite Offline</span>
          </span>
        </div>
      </header>

      {/* Main Tab Body */}
      <main className="flex-1 overflow-y-auto p-4 pb-20">
        {activeTab === 'library' && (
          <div className="flex flex-col gap-4">
            <div className="rounded-2xl border border-slate-800/80 bg-slate-900/40 p-4">
              <span className="rounded bg-brand-500/10 px-2 py-0.5 text-[10px] font-bold text-brand-400 font-mono">
                ZERO NETWORK REQUIRED
              </span>
              <h2 className="mt-2 text-base font-bold text-slate-100">
                Offline Exam Simulator
              </h2>
              <p className="mt-1 text-xs text-slate-400 leading-relaxed">
                Launch authentic practice exams with tactile haptic feedback, OLED pitch black mode, and touch gestures.
              </p>
            </div>

            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 px-1 mt-2">
              Certification Question Banks ({BUNDLED_EXAMS.length})
            </h3>

            <div className="flex flex-col gap-3">
              {BUNDLED_EXAMS.map((exam) => (
                <div
                  key={exam.id}
                  onClick={() => handleStartExam(exam, 'practice')}
                  className="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/60 p-4 active:scale-[0.98] transition-all"
                >
                  <div className="flex items-start gap-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-800 text-xs font-bold text-brand-400 font-mono">
                      {exam.code.slice(0, 3)}
                    </div>
                    <div>
                      <h4 className="text-xs font-bold text-slate-200">{exam.title}</h4>
                      <div className="mt-1.5 flex items-center gap-3 text-[10px] text-slate-400">
                        <span>{exam.questions.length} Items</span>
                        <span>•</span>
                        <span>{exam.timeLimitMinutes} Mins</span>
                        <span>•</span>
                        <span className="text-brand-400 font-semibold">Pass: {exam.passingScore}</span>
                      </div>
                    </div>
                  </div>
                  <ChevronRight className="h-4 w-4 text-slate-600 shrink-0" />
                </div>
              ))}
            </div>
          </div>
        )}

        {activeTab === 'drill' && (
          <div className="flex flex-col gap-4">
            <div className="rounded-2xl border border-brand-500/20 bg-brand-500/5 p-4 text-center">
              <Brain className="mx-auto h-8 w-8 text-brand-400 mb-2" />
              <h3 className="text-sm font-bold text-slate-100">Leitner Spaced Repetition Drill</h3>
              <p className="mt-1 text-xs text-slate-400">
                Daily reviews prioritized by memory decay and Box level (12h, 3d, 7d, 14d, 30d).
              </p>
              <button
                onClick={() => handleStartExam(BUNDLED_EXAMS[0], 'practice')}
                className="mt-4 w-full rounded-xl bg-brand-500 py-2.5 text-xs font-bold text-slate-950 shadow-lg shadow-brand-500/20"
              >
                Launch Daily 5-Item Drill
              </button>
            </div>
          </div>
        )}

        {activeTab === 'sync' && (
          <div className="flex flex-col gap-4 text-xs">
            <div className="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
              <h3 className="font-bold text-slate-200">Local SQLite Database</h3>
              <div className="mt-2 space-y-1 text-slate-400">
                <p>Status: <span className="text-brand-400 font-mono">Encrypted & Synchronized</span></p>
                <p>Attempts Cached: <span className="text-slate-200 font-mono">14 sessions</span></p>
                <p>Sync Queue: <span className="text-slate-200 font-mono">0 pending</span></p>
              </div>
            </div>
          </div>
        )}
      </main>

      {/* Fixed Bottom Touch Tab Navigation (Thumb-Zone) */}
      <nav 
        className="fixed bottom-0 left-0 right-0 z-40 flex h-16 items-center justify-around border-t border-slate-900 bg-slate-950/95 backdrop-blur px-2"
        style={{
          paddingBottom: 'env(safe-area-inset-bottom, 0px)',
        }}
      >
        <button
          onClick={() => { HapticFeedback.selection(); setActiveTab('library'); }}
          className={`flex flex-col items-center gap-1 text-[11px] font-medium transition-colors ${
            activeTab === 'library' ? 'text-brand-400' : 'text-slate-500 hover:text-slate-300'
          }`}
        >
          <Layers className="h-5 w-5" />
          <span>Exams</span>
        </button>

        <button
          onClick={() => { HapticFeedback.selection(); setActiveTab('drill'); }}
          className={`flex flex-col items-center gap-1 text-[11px] font-medium transition-colors ${
            activeTab === 'drill' ? 'text-brand-400' : 'text-slate-500 hover:text-slate-300'
          }`}
        >
          <Brain className="h-5 w-5" />
          <span>Daily Drill</span>
        </button>

        <button
          onClick={() => { HapticFeedback.selection(); setActiveTab('sync'); }}
          className={`flex flex-col items-center gap-1 text-[11px] font-medium transition-colors ${
            activeTab === 'sync' ? 'text-brand-400' : 'text-slate-500 hover:text-slate-300'
          }`}
        >
          <RefreshCw className="h-5 w-5" />
          <span>Offline Sync</span>
        </button>
      </nav>
    </div>
  );
}
