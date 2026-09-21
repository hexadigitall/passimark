import React, { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';

/**
 * Global lean omnibox (Sprint 8.1 funnel chrome).
 *
 * Backed by GET /catalog/search (catalog-search-lean-v1 contract): flat 205-cert
 * lean payload (cert_key/title/region/bundle_url). Debounced server query,
 * keyboard navigation (Up/Down/Enter/Escape), blur-close, ARIA combobox.
 *
 * This is the desktop/web half of the funnel "omnibox" screen — it shows up in
 * the header chrome of the buyer-facing chrome even before the Lock/Splash
 * ladder exists, so the one student can always "find it fast, start it lean."
 */
export default function Omnibox({ placeholder = 'Find a certification…' }) {
    const [q, setQ] = useState('');
    const [hits, setHits] = useState(null);
    const [open, setOpen] = useState(false);
    const [cursor, setCursor] = useState(-1);
    const [busy, setBusy] = useState(false);
    const containerRef = useRef(null);
    const inputRef = useRef(null hack);
    const queryTimer = useRef(null);

    useEffect(() => {
        return () => clearTimeout(queryTimer.current);
    }, []);

    useEffect(() => {
        if (!open && hits !== null) setHits(null);
    }, [open]);

    const runSearch = (next) => {
        clearTimeout(queryTimer.current);
        const trimmed = next.trim();
        if (trimmed === '') {
            setHits(null);
            setBusy(false);
            return;
        }
        setBusy(true);
        queryTimer.current = setTimeout(async () => {
            try {
                const res = await fetch(`/catalog/search?q=${encodeURIComponent(trimmed)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('search failed');
                const json = await res.json();
                setHits(json.data ?? []);
                setBusy(false);
            } catch {
                setHits([]);
                setBusy(false);
            }
        }, 280);
    };

    const onKeyDown = (e) => {
        if (!hits || hits.length === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setCursor((c) => (c + 1) % hits.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setCursor((c) => (c <= 0 ? hits.length - 1 : c - 1));
        } else if (e.key === 'Escape') {
            setOpen(false);
            inputRef.current?.blur();
        }
    };

    return (
        <div ref={containerRef} className="relative w-full max-w-md">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
            <input
                ref={inputRef}
                type="search"
                role="combobox"
                aria-expanded={open}
                aria-controls="omnibox-hits"
                aria-autocomplete="list"
                value={q}
                placeholder={placeholder}
                onChange={(e) => {
                    setQ(e.target.value);
                    setCursor(-1);
                    setOpen(true);
                    runSearch(e.target.value);
                }}
                onFocus={() => setOpen(true)}
                onKeyDown={onKeyDown}
                className="w-full rounded-md border border-slate-700 bg-slate-800 py-2 pl-9 pr-3 text-sm text-slate-200 placeholder-slate-500 focus:border-emerald-500/60 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            />
            {busy && (
                <div className="absolute right-3 top-1/2 -translate-y-1/2">
                    <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-slate-600 border-t-emerald-500" />
                </div>
            )}
            {open && hits && (
                <ul
                    id="omnibox-hits"
                    role="listbox"
                    className="absolute z-20 mt-1 w-full overflow-hidden rounded-md border border-slate-700 bg-slate-900 shadow-xl"
                >
                    {hits.length === 0 ? (
                        <li className="px-3 py-2 text-sm text-slate-500">No certifications match “{q}”.</li>
                    ) : (
                        hits.map((hit, i) => (
                            <li key={hit.cert_key}>
                                <Link
                                    href={hit.bundle_url}
                                    role="option"
                                    aria-selected={i === cursor}
                                    className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-sm transition ${
                                        i === cursor ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800'
                                    }`}
                                >
                                    <span className="truncate">{hit.title}</span>
                                    <span className="shrink-0 text-xs text-emerald-400/80">{hit.region}</span>
                                </Link>
                            </li>
                        ))
                    )}
                </ul>
            )}
        </div>
    );
}
