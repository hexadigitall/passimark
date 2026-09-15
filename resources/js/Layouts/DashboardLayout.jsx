import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { BarChart3, BookOpen, Database, LogOut, Menu, Settings, X } from 'lucide-react';

export default function DashboardLayout({ children }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { auth = {}, flash = {}, regions = [], ability = {} } = usePage().props;
    const user = auth.user;
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '/';
    const [flashVisible, setFlashVisible] = useState(Boolean(flash.success));
    useEffect(() => setFlashVisible(Boolean(flash.success)), [flash.success]);
    const currentRegion = typeof window !== 'undefined'
        ? new URL(window.location.href).searchParams.get('region') || ''
        : '';
    const isStaff = user?.role === 'admin' || user?.role === 'instructor';

    const selectRegion = (region) => {
        router.get('/', region ? { region } : {}, { preserveState: true, replace: true });
    };

    const navigation = isStaff
        ? [
            { href: '/', label: 'Overview', icon: BookOpen },
            { href: '/admin/passimark', label: 'Control Center', icon: Database },
            { href: '/admin/import', label: 'Import Questions', icon: Database },
            { href: '/profile', label: 'Profile', icon: BarChart3 },
            { href: '/settings', label: 'Settings', icon: Settings },
          ]
        : [
            { href: '/', label: 'Dashboard', icon: BookOpen },
            { href: '/profile', label: 'Profile', icon: BarChart3 },
            { href: '/settings', label: 'Settings', icon: Settings },
          ];

    const logout = () => router.post('/logout');

    return (
        <>
            <Head title="Dashboard" />
            <div className="min-h-screen bg-slate-900 lg:flex">
                {/* Sidebar */}
                <aside className={`fixed inset-y-0 left-0 z-50 w-64 shrink-0 overflow-y-auto bg-slate-800 border-r border-slate-700 transform transition-transform duration-300 ease-in-out ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                } lg:translate-x-0 lg:static`}>
                    {/* Logo */}
                    <div className="flex items-center justify-between p-6 border-b border-slate-700">
                        <div className="flex items-center space-x-2">
                            <img src="/images/passimark/passimark_icon_128x128.png" alt="Passimark" className="h-10 w-10" />
                            <span className="text-xl font-bold"><span className="bg-gradient-to-r from-emerald-400 to-teal-500 bg-clip-text text-transparent">Passi</span><span className="text-white">mark</span></span>
                        </div>
                        <button
                            onClick={() => setSidebarOpen(false)}
                            className="lg:hidden rounded p-2 text-slate-400 transition hover:bg-slate-700 hover:text-white"
                            aria-label="Close navigation"
                        >
                            <X className="w-6 h-6" />
                        </button>
                    </div>

                    {/* Navigation */}
                    <nav className="p-6 space-y-2">
                        {navigation.map(({ href, label, icon: Icon }) => (
                            <Link
                                key={label}
                                href={href}
                                onClick={() => setSidebarOpen(false)}
                                className={`flex items-center space-x-3 rounded-lg px-4 py-3 font-medium transition ${
                                    currentPath === href
                                        ? 'bg-emerald-600/20 text-emerald-400'
                                        : 'text-slate-300 hover:bg-slate-700'
                                }`}
                            >
                                <Icon className="h-5 w-5" />
                                <span>{label}</span>
                            </Link>
                        ))}
                    </nav>

                    {/* Divider */}
                    <div className="border-t border-slate-700 my-6"></div>

                    {/* User Section */}
                    <div className="p-6">
                        <div className="mb-4">
                            <p className="text-xs text-slate-500 uppercase tracking-wider">Logged in as</p>
                            <p className="text-sm font-medium text-white truncate">{user?.name}</p>
                            <p className="text-xs text-slate-400 truncate">{user?.email}</p>
                        </div>

                        <div className="mb-4 grid gap-2">
                            <Link
                                href="/profile"
                                className="rounded-lg border border-slate-700 px-3 py-2 text-left text-sm text-slate-300 hover:bg-slate-700"
                            >
                                View profile
                            </Link>
                            <Link
                                href="/settings"
                                className="rounded-lg border border-slate-700 px-3 py-2 text-left text-sm text-slate-300 hover:bg-slate-700"
                            >
                                Account settings
                            </Link>
                        </div>

                        <button
                            type="button"
                            onClick={logout}
                            className="flex w-full items-center justify-center space-x-2 rounded-lg bg-slate-700 px-4 py-2 text-slate-300 transition hover:bg-slate-600"
                        >
                            <LogOut className="h-4 w-4" />
                            <span>Logout</span>
                        </button>
                    </div>
                </aside>

                {/* Main Content */}
                <div className="flex flex-1 min-w-0 flex-col min-h-screen">
                    {/* Top Bar */}
                    <header className="bg-slate-800 border-b border-slate-700">
                        <div className="flex items-center justify-between px-6 py-4">
                            <button
                                onClick={() => setSidebarOpen(true)}
                                className="rounded p-2 text-slate-400 transition hover:bg-slate-700 hover:text-white lg:hidden"
                                aria-label="Open navigation"
                            >
                                <Menu className="w-6 h-6" />
                            </button>

                            <div className="flex-1 lg:flex-none">
                                <h1 className="text-xl font-bold text-white">{isStaff ? 'Passimark Operations' : 'Adaptive Certification Assessment'}</h1>
                            </div>

                            <div className="flex items-center space-x-4">
                                {!isStaff && (
                                <label className="hidden items-center gap-2 text-sm text-slate-400 md:flex">
                                    <span>Region</span>
                                    <select
                                        value={currentRegion}
                                        onChange={(event) => selectRegion(event.target.value)}
                                        className="rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
                                    >
                                        <option value="">All regions</option>
                                        {regions.map((region) => (
                                            <option key={region.name} value={region.name}>{region.name} ({region.count})</option>
                                        ))}
                                    </select>
                                </label>
                                )}

                                {!isStaff && typeof ability.theta === 'number' && (
                                    <span
                                        className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1 font-mono text-xs font-semibold text-emerald-300"
                                        title="Current ability estimate (IRT theta)"
                                    >
                                        θ {ability.theta.toFixed(2)}
                                    </span>
                                )}

                                <div className="text-right hidden sm:block">
                                    <p className="text-sm font-medium text-white">{user?.name}</p>
                                    <p className="text-xs capitalize text-slate-400">{user?.role}</p>
                                </div>
                            </div>
                        </div>
                    </header>

                    {/* Page Content */}
                    <main className="flex-1 p-6">
                        {flashVisible && flash.success && (
                            <div className="mb-4 flex items-start justify-between gap-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                                <span>{flash.success}</span>
                                <button type="button" onClick={() => setFlashVisible(false)} aria-label="Dismiss" className="text-emerald-300 hover:text-white">
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        )}
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
