import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { 
    ArrowLeft, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle, 
    Download, 
    Sparkles, 
    FileArchive, 
    Layers, 
    Check, 
    Image as ImageIcon,
    Sliders,
    BookOpen
} from 'lucide-react';
import DashboardLayout from '../../../Layouts/DashboardLayout';

export default function ImportShow({ batch, sessions = [] }) {
    const [filter, setFilter] = useState('all'); // all, valid, error, approved
    const [selectedSession, setSelectedSession] = useState(sessions[0]?.id || '');
    const [busyAction, setBusyAction] = useState('');
    const [exportMessage, setExportMessage] = useState('');

    const items = batch.items || [];

    const filteredItems = items.filter((item) => {
        if (filter === 'valid') return item.status === 'valid';
        if (filter === 'error') return item.status === 'error';
        if (filter === 'approved') return item.status === 'approved';
        return true;
    });

    const handleApproveAll = async () => {
        setBusyAction('approve_all');
        router.post(`/admin/imports/${batch.id}/review`, { action: 'approve_all' }, {
            preserveScroll: true,
            onFinish: () => setBusyAction(''),
        });
    };

    const handleApproveItem = (itemId) => {
        router.post(`/admin/imports/${batch.id}/review`, { action: 'approve', item_id: itemId }, {
            preserveScroll: true,
        });
    };

    const handleRejectItem = (itemId) => {
        router.post(`/admin/imports/${batch.id}/review`, { action: 'reject', item_id: itemId }, {
            preserveScroll: true,
        });
    };

    const handleExportPsmk = () => {
        window.location.href = `/admin/imports/${batch.id}/export?download=1`;
    };

    const handleImportToSession = () => {
        if (!selectedSession) return;
        setBusyAction('import_session');
        router.post(`/admin/imports/${batch.id}/export`, { session_id: selectedSession }, {
            preserveScroll: true,
            onSuccess: (page) => {
                setBusyAction('');
                setExportMessage(`Successfully imported questions into target session.`);
            },
            onFinish: () => setBusyAction(''),
        });
    };

    const validCount = items.filter(i => i.status === 'valid').length;
    const errorCount = items.filter(i => i.status === 'error').length;
    const approvedCount = items.filter(i => i.status === 'approved').length;

    return (
        <DashboardLayout>
            <Head title={`Staged Batch - ${batch.metadata?.title || batch.original_filename}`} />

            <div className="space-y-6">
                {/* Navigation & Header */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <Link 
                            href="/admin/imports" 
                            className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white transition-colors mb-2"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" /> Back to All Import Batches
                        </Link>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-slate-800 px-2 py-0.5 text-xs font-mono font-medium text-brand-400">
                                {batch.source_type.toUpperCase()}
                            </span>
                            <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${
                                batch.status === 'converted' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' :
                                'bg-blue-500/20 text-blue-400 border border-blue-500/30'
                            }`}>
                                {batch.status.toUpperCase()}
                            </span>
                        </div>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                            {batch.metadata?.title || batch.original_filename}
                        </h1>
                        <p className="text-xs text-slate-400 font-mono mt-0.5">
                            SHA-256: {batch.checksum.substring(0, 16)}... | {items.length} items parsed
                            {batch.metadata?.asset_count > 0 && ` | ${batch.metadata.asset_count} diagram asset(s) extracted`}
                        </p>
                    </div>

                    {/* Primary Actions */}
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            onClick={handleApproveAll}
                            disabled={busyAction === 'approve_all' || validCount === 0}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700 hover:text-white disabled:opacity-50 transition-colors"
                        >
                            <Check className="h-4 w-4 text-emerald-400" />
                            {busyAction === 'approve_all' ? 'Approving...' : `Approve All Valid (${validCount})`}
                        </button>

                        <button
                            onClick={handleExportPsmk}
                            className="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-xs font-semibold text-slate-950 hover:bg-brand-400 transition-colors shadow-lg shadow-brand-500/20"
                        >
                            <Download className="h-4 w-4" />
                            Export .psmk Package
                        </button>
                    </div>
                </div>

                {exportMessage && (
                    <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-300 flex items-center justify-between">
                        <span>{exportMessage}</span>
                        <button onClick={() => setExportMessage('')} className="text-emerald-400 hover:text-white">&times;</button>
                    </div>
                )}

                {/* Direct Ingestion into Session Card */}
                {sessions.length > 0 && (
                    <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <BookOpen className="h-5 w-5 text-brand-400 shrink-0" />
                            <div>
                                <h3 className="text-sm font-semibold text-white">Direct Curriculum Placement</h3>
                                <p className="text-xs text-slate-400">Directly inject all approved questions from this batch into a course module</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <select
                                value={selectedSession}
                                onChange={(e) => setSelectedSession(e.target.value)}
                                className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-1.5 text-xs text-white"
                            >
                                {sessions.map(s => (
                                    <option key={s.id} value={s.id}>
                                        {s.number ? `${s.number}. ` : ''}{s.title}
                                    </option>
                                ))}
                            </select>
                            <button
                                onClick={handleImportToSession}
                                disabled={busyAction === 'import_session' || (validCount + approvedCount === 0)}
                                className="rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-500 hover:text-slate-950 disabled:opacity-50 transition-colors"
                            >
                                {busyAction === 'import_session' ? 'Injecting...' : 'Inject into Session'}
                            </button>
                        </div>
                    </div>
                )}

                {/* Filter Pills */}
                <div className="flex items-center gap-2 border-b border-slate-800 pb-3">
                    <button
                        onClick={() => setFilter('all')}
                        className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                            filter === 'all' ? 'bg-brand-500 text-slate-950' : 'bg-slate-800 text-slate-400 hover:text-white'
                        }`}
                    >
                        All Items ({items.length})
                    </button>
                    <button
                        onClick={() => setFilter('valid')}
                        className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                            filter === 'valid' ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-400 hover:text-white'
                        }`}
                    >
                        Valid ({validCount})
                    </button>
                    <button
                        onClick={() => setFilter('approved')}
                        className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                            filter === 'approved' ? 'bg-blue-500 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'
                        }`}
                    >
                        Approved ({approvedCount})
                    </button>
                    {errorCount > 0 && (
                        <button
                            onClick={() => setFilter('error')}
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                                filter === 'error' ? 'bg-red-500 text-white' : 'bg-slate-800 text-red-400 hover:text-red-300'
                            }`}
                        >
                            Errors ({errorCount})
                        </button>
                    )}
                </div>

                {/* Questions Grid / List */}
                <div className="space-y-4">
                    {filteredItems.length === 0 ? (
                        <div className="rounded-2xl border border-slate-800 bg-slate-900/40 p-12 text-center text-slate-400">
                            No questions match the selected filter.
                        </div>
                    ) : (
                        filteredItems.map((item) => (
                            <div 
                                key={item.id}
                                className={`rounded-xl border p-5 transition-colors ${
                                    item.status === 'approved' ? 'border-blue-500/30 bg-slate-900/70' :
                                    item.status === 'error' ? 'border-red-500/40 bg-red-950/10' :
                                    'border-slate-800 bg-slate-900/50'
                                }`}
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="space-y-2 flex-1">
                                        {/* Badges & Meta */}
                                        <div className="flex flex-wrap items-center gap-2 text-xs">
                                            <span className="font-mono font-bold text-slate-400">#{item.source_index}</span>
                                            <span className="rounded bg-slate-800 px-2 py-0.5 font-semibold text-brand-400">
                                                {item.item_type}
                                            </span>
                                            <span className="text-slate-400">|</span>
                                            <span className="text-slate-400">{item.domain || 'General Domain'}</span>
                                            <span className="text-slate-400">|</span>
                                            <span className="text-slate-400">Bloom: {item.bloom || 'Apply'}</span>
                                            <span className="text-slate-400">|</span>
                                            <span className="font-mono text-slate-400">IRT: b={item.difficulty}, a={item.discrimination}</span>
                                        </div>

                                        {/* Prompt */}
                                        <h3 className="text-base font-medium text-white leading-relaxed">
                                            {item.prompt}
                                        </h3>

                                        {/* Diagram Exhibits if present */}
                                        {item.exhibits && item.exhibits.length > 0 && (
                                            <div className="flex items-center gap-2 p-2 rounded-lg bg-slate-950 border border-slate-800 text-xs text-brand-400">
                                                <ImageIcon className="h-4 w-4" />
                                                <span>Embedded Exhibit Diagram: {item.exhibits[0].title || item.exhibits[0].path}</span>
                                            </div>
                                        )}

                                        {/* Choices */}
                                        {item.choices && item.choices.length > 0 && (
                                            <div className="mt-3 space-y-1.5">
                                                {item.choices.map((choice) => (
                                                    <div 
                                                        key={choice.id}
                                                        className={`flex items-start gap-2.5 rounded-lg px-3 py-2 text-sm border transition-colors ${
                                                            choice.is_correct 
                                                                ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200 font-medium'
                                                                : 'border-slate-800/80 bg-slate-950/60 text-slate-300'
                                                        }`}
                                                    >
                                                        <span className={`inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-xs font-bold ${
                                                            choice.is_correct ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-400'
                                                        }`}>
                                                            {choice.id}
                                                        </span>
                                                        <span className="flex-1">{choice.text}</span>
                                                        {choice.is_correct && (
                                                            <CheckCircle2 className="h-4 w-4 text-emerald-400 shrink-0 mt-0.5" />
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        {/* Explanation */}
                                        {item.explanation && (
                                            <div className="mt-2 rounded-lg bg-slate-950/60 p-3 text-xs text-slate-400 border border-slate-800">
                                                <strong className="text-slate-300">Explanation: </strong>
                                                {item.explanation}
                                            </div>
                                        )}

                                        {/* Validation Errors */}
                                        {item.validation_errors && item.validation_errors.length > 0 && (
                                            <div className="mt-2 rounded-lg border border-red-500/30 bg-red-500/10 p-2.5 text-xs text-red-300 flex items-start gap-2">
                                                <AlertTriangle className="h-4 w-4 text-red-400 shrink-0 mt-0.5" />
                                                <div>
                                                    <p className="font-semibold">Validation Errors:</p>
                                                    <ul className="list-disc list-inside">
                                                        {item.validation_errors.map((err, errIdx) => (
                                                            <li key={errIdx}>{err}</li>
                                                        ))}
                                                    </ul>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Action buttons */}
                                    <div className="flex sm:flex-col items-center gap-2 shrink-0">
                                        {item.status !== 'approved' ? (
                                            <button
                                                onClick={() => handleApproveItem(item.id)}
                                                className="inline-flex items-center gap-1 rounded-md bg-emerald-500/20 border border-emerald-500/40 px-2.5 py-1 text-xs font-semibold text-emerald-300 hover:bg-emerald-500 hover:text-slate-950 transition-colors"
                                            >
                                                <Check className="h-3 w-3" /> Approve
                                            </button>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-blue-500/20 px-2.5 py-0.5 text-xs font-semibold text-blue-300 border border-blue-500/40">
                                                Approved
                                            </span>
                                        )}

                                        {item.status !== 'rejected' && (
                                            <button
                                                onClick={() => handleRejectItem(item.id)}
                                                className="inline-flex items-center gap-1 rounded-md bg-red-500/10 border border-red-500/20 px-2.5 py-1 text-xs font-semibold text-red-400 hover:bg-red-500 hover:text-white transition-colors"
                                            >
                                                <XCircle className="h-3 w-3" /> Reject
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}
