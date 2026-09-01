
import React from 'react';
export default function Admin({pending, sessions}){
  return (
    <div className="p-6 bg-slate-900 min-h-screen text-white">
      <h1 className="text-2xl font-bold">Passimark Control Center</h1>
      <h2 className="mt-6 font-semibold">Pending Approvals: {pending.length}</h2>
      {pending.map(p=> <div key={p.id} className="mt-2 p-3 bg-slate-800 rounded">{p.user?.name} - {p.session?.title} - Score {p.score}% <button className="ml-3 bg-green-600 px-3 py-1 rounded">Approve</button></div>)}
    </div>
  )
}
