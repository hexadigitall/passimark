import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { 
    Upload, 
    FileArchive, 
    FileText, 
    CheckCircle2, 
    AlertTriangle, 
    ArrowRight, 
    Trash2, 
    Sparkles, 
    Layers, 
    DownloadCloud 
} from 'lucide-react';
import DashboardLayout from '../../../Layouts/DashboardLayout';

export default function ImportIndex({ batches, sessions = [] }) {
    const [file, setFile] = useState(null);
    const [sourceType, setSourceType] = useState('auto');
    const [title, setTitle] = useState('');
    const [certSlug, setCertSlug] = useState('');
    const [timeMinutes, setTimeMinutes] = useState(90);
    const [passScore, setPassScore] = useState(70);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState('');

    const handleFileChange = (e) => {
        const selected = e.target.files[0];
        if (!selected) return;
        setFile(selected);
        setError('');

        const ext = selected.name.split('.').pop().toLowerCase();
        if (ext === 'vce') setSourceType('vce');
        else if (ext === 'csv' || ext === 'tsv') setSourceType('csv');
        else if (ext === 'gift' || ext === 'txt') setSourceType('gift');
        else if (ext === 'xml') setSourceType('moodle_xml');
        else if (ext === 'qti' || ext === 'zip') setSourceType('qti');

        if (!title) {
            const rawName = selected.name.replace(/\.[^/.]+$/, "");
            setTitle(rawName.replace(/[-_]/g, ' '));
        }
    };

    const handleUpload = (e) => {
        e.preventDefault();
        if (!file) {
            setError('Please select a file to import.');
            return;
        }

        setUploading(true);
        setError('');

        const formData = new FormData();
        formData.append('file', file);
        formData.append('source_type', sourceType);
        if (title) formData.append('title', title);
        if (certSlug) formData.append('cert_slug', certSlug);
        if (timeMinutes) formData.append('time_minutes', timeMinutes);
        if (passScore) formData.append('pass_score', passScore);

        router.post('/admin/imports/upload', formData, {
            onSuccess: () => {
                setUploading(false);
                setFile(null);
                setTitle('');
            },
            onError: (errs) => {
                setUploading(false);
                setError(Object.values(errs).flat().join(' ') || 'Upload failed.');
            },
        });
    };

    const handleDelete = (id, filename) => {
        if (!confirm(`Are you sure you want to delete staged batch "${filename}"?`)) return;
        router.delete(`/admin/imports/${id}`);
    };

    const batchList = batches?.data || batches || [];

    return (
        <DashboardLayout>
            <Head title="Universal Importer & VCE Converter" />

            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-400 border border-brand-500/20">
                                <Sparkles className="h-3.5 w-3.5" /> Next-Gen Universal Ingestion
                            </span>
                        </div>
                        <h1 className="mt-2 text-3xl font-bold tracking-tight text-white">
                            Universal Importer & VCE Converter
                        </h1>
                        <p className="mt-1 text-sm text-slate-400 max-w-2xl">
                            Migrate legacy Visual CertExam (<code className="text-brand-300">.vce</code>) binaries, Moodle XML, GIFT, CSV, and IMS QTI files directly into psychometrically calibrated Passimark assessment packages.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2 text-xs font-mono">
                        <span className="rounded bg-slate-900 px-2.5 py-1 text-slate-300 border border-slate-800">.vce (VCE Player/Designer)</span>
                        <span className="rounded bg-slate-900 px-2.5 py-1 text-slate-300 border border-slate-800">.csv / .tsv</span>
                        <span className="rounded bg-slate-900 px-2.5 py-1 text-slate-300 border border-slate-800">.gift</span>
                        <span className="rounded bg-slate-900 px-2.5 py-1 text-slate-300 border border-slate-800">.xml (Moodle)</span>
                        <span className="rounded bg-slate-900 px-2.5 py-1 text-slate-300 border border-slate-800">.qti / .zip</span>
                    </div>
                </div>

                {/* Upload Section */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 backdrop-blur">
                    <h2 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                        <Upload className="h-5 w-5 text-brand-400" />
                        Stage New Source Assessment File
                    </h2>

                    <form onSubmit={handleUpload} className="space-y-6">
                        {/* Drag and Drop Zone */}
                        <div className="relative rounded-xl border-2 border-dashed border-slate-700 hover:border-brand-500/60 bg-slate-950/60 p-8 text-center transition-colors">
                            <input
                                type="file"
                                id="sourceFile"
                                onChange={handleFileChange}
                                className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                accept=".vce,.csv,.tsv,.gift,.txt,.xml,.qti,.zip"
                            />
                            <div className="flex flex-col items-center justify-center gap-2">
                                <div className="rounded-full bg-slate-800 p-3 text-brand-400 shadow">
                                    <FileArchive className="h-6 w-6" />
                                </div>
                                <div className="text-sm font-medium text-slate-200">
                                    {file ? (
                                        <span className="text-brand-400 font-semibold">{file.name} ({(file.size / 1024).toFixed(1)} KB)</span>
                                    ) : (
                                        <span>Click to browse or drop your <strong className="text-brand-400">.vce</strong>, .csv, .gift, or .xml file here</span>
                                    )}
                                </div>
                                <p className="text-xs text-slate-400">Supports standard binary VCE containers, Moodle exports, and CSV item pools up to 50MB</p>
                            </div>
                        </div>

                        {error && (
                            <div className="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-300 flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 text-red-400 shrink-0" />
                                {error}
                            </div>
                        )}

                        {/* Staging Parameters */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-300">Format Adapter</label>
                                <select
                                    value={sourceType}
                                    onChange={(e) => setSourceType(e.target.value)}
                                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-brand-500"
                                >
                                    <option value="auto">Auto-Detect by Extension</option>
                                    <option value="vce">Visual CertExam (.vce Binary)</option>
                                    <option value="csv">Standard CSV / TSV</option>
                                    <option value="gift">Moodle GIFT Format</option>
                                    <option value="moodle_xml">Moodle XML (with Diagrams)</option>
                                    <option value="qti">IMS QTI 2.x/3.x XML/ZIP</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-300">Exam Title</label>
                                <input
                                    type="text"
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                    placeholder="e.g. Cisco CCNA 200-301 Practice Exam"
                                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-brand-500"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-300">Time Limit (Minutes)</label>
                                <input
                                    type="number"
                                    value={timeMinutes}
                                    onChange={(e) => setTimeMinutes(e.target.value)}
                                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-brand-500"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-300">Passing Score (%)</label>
                                <input
                                    type="number"
                                    value={passScore}
                                    onChange={(e) => setPassScore(e.target.value)}
                                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-brand-500"
                                />
                            </div>
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={uploading || !file}
                                className="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-semibold text-slate-950 hover:bg-brand-400 disabled:opacity-50 transition-colors shadow-lg shadow-brand-500/20"
                            >
                                <Upload className="h-4 w-4" />
                                {uploading ? 'Parsing & Staging Container...' : 'Parse & Stage Exam'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Staged Batches List */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 backdrop-blur">
                    <h2 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                        <Layers className="h-5 w-5 text-brand-400" />
                        Staged Assessment Batches ({batchList.length})
                    </h2>

                    {batchList.length === 0 ? (
                        <div className="text-center py-12 text-slate-400">
                            <FileArchive className="h-10 w-10 mx-auto text-slate-600 mb-2" />
                            <p className="font-medium">No staged import batches yet.</p>
                            <p className="text-xs text-slate-500 mt-1">Upload a .vce or .csv file above to begin validation and staging.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-300">
                                <thead className="border-b border-slate-800 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                    <tr>
                                        <th className="py-3 px-4">Source File & Title</th>
                                        <th className="py-3 px-4">Adapter</th>
                                        <th className="py-3 px-4">Status</th>
                                        <th className="py-3 px-4 text-center">Items Staged</th>
                                        <th className="py-3 px-4 text-center">Valid / Errors</th>
                                        <th className="py-3 px-4">Created</th>
                                        <th className="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800/60">
                                    {batchList.map((batch) => (
                                        <tr key={batch.id} className="hover:bg-slate-800/40 transition-colors">
                                            <td className="py-3 px-4">
                                                <div className="font-medium text-white">{batch.metadata?.title || batch.original_filename}</div>
                                                <div className="text-xs text-slate-400 font-mono">{batch.original_filename}</div>
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className="inline-block rounded bg-slate-800 px-2 py-0.5 text-xs font-mono font-medium text-brand-400">
                                                    {batch.source_type.toUpperCase()}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                    batch.status === 'converted' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' :
                                                    batch.status === 'staged' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' :
                                                    'bg-amber-500/20 text-amber-400 border border-amber-500/30'
                                                }`}>
                                                    {batch.status.toUpperCase()}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-center font-semibold text-white">
                                                {batch.total_items}
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <span className="text-emerald-400 font-semibold">{batch.valid_items}</span>
                                                <span className="text-slate-500"> / </span>
                                                <span className={batch.error_items > 0 ? "text-red-400 font-semibold" : "text-slate-500"}>
                                                    {batch.error_items}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-xs text-slate-400">
                                                {new Date(batch.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <div className="inline-flex items-center gap-2">
                                                    <Link
                                                        href={`/admin/imports/${batch.id}`}
                                                        className="inline-flex items-center gap-1 rounded-md bg-slate-800 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-brand-500 hover:text-slate-950 transition-colors"
                                                    >
                                                        Review & Convert
                                                        <ArrowRight className="h-3.5 w-3.5" />
                                                    </Link>
                                                    <button
                                                        onClick={() => handleDelete(batch.id, batch.original_filename)}
                                                        className="p-1.5 text-slate-500 hover:text-red-400 transition-colors"
                                                        title="Delete batch"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}
