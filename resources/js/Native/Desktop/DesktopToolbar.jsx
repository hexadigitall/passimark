import React, { useState } from 'react';
import { 
  FileText, Play, Edit3, Monitor, Layers, Calculator, 
  HelpCircle, ExternalLink, Settings, Sparkles, FolderOpen, Save, RefreshCw
} from 'lucide-react';
import { TauriBridge } from '../../Services/Desktop/TauriBridge';

export default function DesktopToolbar({
  activeMode,
  onModeChange,
  activeExam,
  onOpenLibrary,
  onOpenFile,
  onOpenCalculator,
  onOpenScratchpad,
  onOpenShortcuts,
  onResetExam,
}) {
  const [activeMenu, setActiveMenu] = useState(null);

  const toggleMenu = (name) => {
    setActiveMenu(activeMenu === name ? null : name);
  };

  const closeMenu = () => setActiveMenu(null);

  return (
    <header className="flex h-11 select-none items-center justify-between border-b border-slate-800 bg-slate-900 px-3 text-xs text-slate-300">
      {/* Left: Brand + Native Menus */}
      <div className="flex items-center gap-4">
        <div className="flex items-center gap-2 pr-2 border-r border-slate-800">
          <div className="flex h-6 w-6 items-center justify-center rounded bg-brand-500 font-bold text-slate-950 text-xs">
            PM
          </div>
          <span className="font-semibold text-slate-100 tracking-tight">Passimark Desktop</span>
          <span className="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] text-brand-400 font-mono">v2.0 (Tauri Core)</span>
        </div>

        {/* Native OS Menu Dropdowns */}
        <div className="flex items-center gap-1 relative">
          {/* File Menu */}
          <div className="relative">
            <button 
              onClick={() => toggleMenu('file')}
              className={`rounded px-2.5 py-1 text-slate-300 hover:bg-slate-800 hover:text-white transition-colors ${activeMenu === 'file' ? 'bg-slate-800 text-white' : ''}`}
            >
              File
            </button>
            {activeMenu === 'file' && (
              <div 
                className="absolute left-0 top-full mt-1 w-56 rounded-md border border-slate-800 bg-slate-900 py-1 shadow-xl z-50 text-xs"
                onMouseLeave={closeMenu}
              >
                <button 
                  onClick={() => { closeMenu(); onOpenFile(); }}
                  className="flex w-full items-center justify-between px-3 py-1.5 text-left hover:bg-slate-800 text-slate-200"
                >
                  <span className="flex items-center gap-2"><FolderOpen className="h-3.5 w-3.5 text-brand-400" /> Open Exam (.psmk, .vce)...</span>
                  <span className="text-[10px] text-slate-500 font-mono">Ctrl+O</span>
                </button>
                <button 
                  onClick={() => { closeMenu(); onOpenLibrary(); }}
                  className="flex w-full items-center justify-between px-3 py-1.5 text-left hover:bg-slate-800 text-slate-200"
                >
                  <span className="flex items-center gap-2"><Layers className="h-3.5 w-3.5 text-blue-400" /> Exam Library</span>
                  <span className="text-[10px] text-slate-500 font-mono">Ctrl+L</span>
                </button>
                <div className="my-1 border-t border-slate-800" />
                <button 
                  onClick={() => { closeMenu(); onResetExam?.(); }}
                  disabled={!activeExam}
                  className="flex w-full items-center justify-between px-3 py-1.5 text-left hover:bg-slate-800 text-slate-200 disabled:opacity-40"
                >
                  <span className="flex items-center gap-2"><RefreshCw className="h-3.5 w-3.5 text-amber-400" /> Reset Exam Session</span>
                </button>
              </div>
            )}
          </div>

          {/* Tools Menu */}
          <div className="relative">
            <button 
              onClick={() => toggleMenu('tools')}
              className={`rounded px-2.5 py-1 text-slate-300 hover:bg-slate-800 hover:text-white transition-colors ${activeMenu === 'tools' ? 'bg-slate-800 text-white' : ''}`}
            >
              Tools
            </button>
            {activeMenu === 'tools' && (
              <div 
                className="absolute left-0 top-full mt-1 w-64 rounded-md border border-slate-800 bg-slate-900 py-1 shadow-xl z-50 text-xs"
                onMouseLeave={closeMenu}
              >
                <button 
                  onClick={() => { closeMenu(); onOpenCalculator(); }}
                  className="flex w-full items-center justify-between px-3 py-1.5 text-left hover:bg-slate-800 text-slate-200"
                >
                  <span className="flex items-center gap-2"><Calculator className="h-3.5 w-3.5 text-amber-400" /> Scientific & Subnet Calculator</span>
                  <span className="text-[10px] text-slate-500 font-mono">Ctrl+K</span>
                </button>
                <button 
                  onClick={() => { closeMenu(); onOpenScratchpad(); }}
                  className="flex w-full items-center justify-between px-3 py-1.5 text-left hover:bg-slate-800 text-slate-200"
                >
                  <span className="flex items-center gap-2"><FileText className="h-3.5 w-3.5 text-purple-400" /> Detach Scratchpad Window</span>
                  <span className="text-[10px] text-slate-500 font-mono">Alt+N</span>
                </button>
                <div className="my-1 border-t border-slate-800" />
                <button 
                  onClick={() => { closeMenu(); onOpenShortcuts(); }}
                  className="flex w-full items-center justify-between px-3 py-1.5 text-left hover:bg-slate-800 text-slate-200"
                >
                  <span className="flex items-center gap-2"><HelpCircle className="h-3.5 w-3.5 text-cyan-400" /> Keyboard Shortcuts</span>
                  <span className="text-[10px] text-slate-500 font-mono">F1</span>
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Center: Mode Switcher (VCE Player Simulator vs VCE Designer Studio) */}
      <div className="flex items-center rounded-lg bg-slate-950 p-0.5 border border-slate-800">
        <button
          onClick={() => onModeChange('player')}
          className={`flex items-center gap-1.5 rounded-md px-3 py-1 font-medium transition-all ${
            activeMode === 'player'
              ? 'bg-brand-500 text-slate-950 shadow'
              : 'text-slate-400 hover:text-slate-200'
          }`}
        >
          <Play className="h-3.5 w-3.5" />
          <span>VCE Player</span>
        </button>
        <button
          onClick={() => onModeChange('designer')}
          className={`flex items-center gap-1.5 rounded-md px-3 py-1 font-medium transition-all ${
            activeMode === 'designer'
              ? 'bg-brand-500 text-slate-950 shadow'
              : 'text-slate-400 hover:text-slate-200'
          }`}
        >
          <Edit3 className="h-3.5 w-3.5" />
          <span>VCE Designer</span>
        </button>
      </div>

      {/* Right: Active Exam Indicator & Window Info */}
      <div className="flex items-center gap-3">
        {activeExam ? (
          <div className="flex items-center gap-2 text-[11px] text-slate-400">
            <span className="inline-block h-2 w-2 rounded-full bg-brand-400 animate-pulse" />
            <span className="font-medium text-slate-200 truncate max-w-xs">{activeExam.title}</span>
          </div>
        ) : (
          <span className="text-[11px] text-slate-500">No active exam loaded</span>
        )}
      </div>
    </header>
  );
}
