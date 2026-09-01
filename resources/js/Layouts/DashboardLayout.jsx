import React, { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, BarChart3, Settings, LogOut, Menu, X } from 'lucide-react';

export default function DashboardLayout({ children }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="min-h-screen bg-slate-900">
                {/* Sidebar */}
                <aside className={`fixed inset-y-0 left-0 z-50 w-64 bg-slate-800 border-r border-slate-700 transform transition-transform duration-300 ease-in-out ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                } lg:translate-x-0 lg:static`}>
                    {/* Logo */}
                    <div className="flex items-center justify-between p-6 border-b border-slate-700">
                        <div className="flex items-center space-x-2">
                            <BookOpen className="w-8 h-8 text-emerald-400" />
                            <span className="text-xl font-bold text-white">Passimark</span>
                        </div>
                        <button
                            onClick={() => setSidebarOpen(false)}
                            className="lg:hidden text-slate-400 hover:text-white"
                        >
                            <X className="w-6 h-6" />
                        </button>
                    </div>

                    {/* Navigation */}
                    <nav className="p-6 space-y-2">
                        <Link
                            href={route('dashboard')}
                            className="flex items-center space-x-3 px-4 py-3 rounded-lg bg-emerald-600/20 text-emerald-400 font-medium"
                        >
                            <BookOpen className="w-5 h-5" />
                            <span>Dashboard</span>
                        </Link>

                        <Link
                            href="#"
                            className="flex items-center space-x-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700 transition"
                        >
                            <BarChart3 className="w-5 h-5" />
                            <span>Progress</span>
                        </Link>

                        <Link
                            href="#"
                            className="flex items-center space-x-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700 transition"
                        >
                            <Settings className="w-5 h-5" />
                            <span>Settings</span>
                        </Link>
                    </nav>

                    {/* Divider */}
                    <div className="border-t border-slate-700 my-6"></div>

                    {/* User Section */}
                    <div className="p-6">
                        <div className="mb-4">
                            <p className="text-xs text-slate-500 uppercase tracking-wider">Logged in as</p>
                            <p className="text-sm font-medium text-white truncate">{auth.user.name}</p>
                            <p className="text-xs text-slate-400 truncate">{auth.user.email}</p>
                        </div>

                        <form method="POST" action={route('logout')} className="w-full">
                            <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content} />
                            <button
                                type="submit"
                                className="w-full flex items-center justify-center space-x-2 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition"
                            >
                                <LogOut className="w-4 h-4" />
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </aside>

                {/* Main Content */}
                <div className="lg:ml-0 flex flex-col min-h-screen">
                    {/* Top Bar */}
                    <header className="bg-slate-800 border-b border-slate-700">
                        <div className="flex items-center justify-between px-6 py-4">
                            <button
                                onClick={() => setSidebarOpen(true)}
                                className="lg:hidden text-slate-400 hover:text-white"
                            >
                                <Menu className="w-6 h-6" />
                            </button>

                            <div className="flex-1 lg:flex-none">
                                <h1 className="text-xl font-bold text-white">Adaptive Certification Assessment</h1>
                            </div>

                            <div className="flex items-center space-x-4">
                                <div className="text-right hidden sm:block">
                                    <p className="text-sm font-medium text-white">{auth.user.name}</p>
                                    <p className="text-xs text-slate-400">{auth.user.role}</p>
                                </div>
                            </div>
                        </div>
                    </header>

                    {/* Page Content */}
                    <main className="flex-1 p-6">
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
