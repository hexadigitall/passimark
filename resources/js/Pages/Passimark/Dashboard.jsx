
import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
// This is placeholder - full React component is in the interactive artifact
// See artifact for complete implementation
export default function Dashboard({sessions, progress}){
  return (
    <div className="min-h-screen bg-[#0F172A] text-white p-6">
      <h1 className="text-3xl font-bold bg-gradient-to-r from-sky-400 to-indigo-500 bg-clip-text text-transparent">Passimark</h1>
      <p className="text-slate-400">Adaptive Exam Intelligence - Full UI is in the artifact preview. This file is scaffold for Laravel Inertia.</p>
      <div className="mt-6 grid gap-4">
        {sessions.map(s=>{
          const prog = progress[s.id];
          return <div key={s.id} className="p-4 rounded-xl bg-slate-800 border border-slate-700">
            <div className="flex justify-between"><span>{s.title}</span><span className="text-xs px-2 py-1 rounded bg-sky-500/20 text-sky-400">{prog?.status||'locked'}</span></div>
            <div className="text-xs text-slate-500 mt-1">Phase {s.phase} • {s.domain} • Pass {s.pass_score}%</div>
          </div>
        })}
      </div>
    </div>
  )
}
