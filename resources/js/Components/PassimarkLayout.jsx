
import React from 'react';

export function PassimarkHeader(){
  return (
    <header className="sticky top-0 z-50 backdrop-blur-xl bg-[#0F172A]/80 border-b border-slate-800">
      <div className="max-w-7xl mx-auto px-6 py-3 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <img src="/images/passimark/passimark_final_logo.png" alt="Passimark" className="w-10 h-10 rounded-full shadow-lg" />
          <div>
            <h1 className="text-xl font-bold tracking-tight">
              <span className="bg-gradient-to-r from-green-400 to-emerald-600 bg-clip-text text-transparent">PASSI</span>
              <span className="text-white">MARK</span>
            </h1>
            <p className="text-[10px] text-slate-400 -mt-1 tracking-widest">ADAPTIVE EXAM INTELLIGENCE</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
          <span className="text-xs text-slate-400">LIVE CAT ENGINE</span>
        </div>
      </div>
    </header>
  )
}

export function PassimarkFooter(){
  return (
    <footer className="border-t border-slate-800 mt-12 py-6 text-center">
      <div className="flex items-center justify-center gap-2">
        <img src="/images/passimark/passimark_icon_32x32.png" alt="P" className="w-5 h-5" />
        <span className="text-xs text-slate-500">© 2026 Passimark • P = Good Mark • Solid = Mastered, Dotted = Becoming Solid</span>
      </div>
    </footer>
  )
}
