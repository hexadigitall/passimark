import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Lock, Mail, UserRound } from 'lucide-react';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post('/register');
    };

    return (
        <>
            <Head title="Create your account" />
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
            <div className="absolute inset-0 overflow-hidden">
                <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-emerald-500 opacity-20 mix-blend-multiply blur-3xl"></div>
                <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-teal-500 opacity-20 mix-blend-multiply blur-3xl"></div>
            </div>
            <main className="relative w-full max-w-md">
                <header className="mb-8 text-center">
                    <h1 className="mb-2 text-4xl font-bold text-white">Passimark</h1>
                    <p className="text-slate-400">Create your learner account</p>
                </header>

                <form onSubmit={submit} className="rounded-2xl border border-slate-700/50 bg-slate-800/80 p-8 shadow-2xl backdrop-blur">
                    <Field
                        id="name"
                        label="Name"
                        icon={UserRound}
                        value={data.name}
                        error={errors.name}
                        disabled={processing}
                        onChange={(value) => setData('name', value)}
                        autoComplete="name"
                    />
                    <Field
                        id="email"
                        label="Email address"
                        icon={Mail}
                        type="email"
                        value={data.email}
                        error={errors.email}
                        disabled={processing}
                        onChange={(value) => setData('email', value)}
                        autoComplete="email"
                    />
                    <Field
                        id="password"
                        label="Password"
                        icon={Lock}
                        type="password"
                        value={data.password}
                        error={errors.password}
                        disabled={processing}
                        onChange={(value) => setData('password', value)}
                        autoComplete="new-password"
                    />
                    <Field
                        id="password_confirmation"
                        label="Confirm password"
                        icon={Lock}
                        type="password"
                        value={data.password_confirmation}
                        disabled={processing}
                        onChange={(value) => setData('password_confirmation', value)}
                        autoComplete="new-password"
                    />

                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-2 flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-emerald-500 px-4 py-3 font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:bg-slate-600 disabled:text-slate-300"
                    >
                        {processing ? 'Creating account...' : 'Create account'}
                        {!processing && <ArrowRight className="h-4 w-4" />}
                    </button>

                    <p className="mt-5 text-center text-sm text-slate-400">
                        Already have an account?{' '}
                        <Link href="/login" className="font-medium text-emerald-400 hover:text-emerald-300">
                            Sign in
                        </Link>
                    </p>
                </form>
            </main>
        </div>
        </>
    );
}

function Field({ autoComplete, disabled, error, icon: Icon, id, label, onChange, type = 'text', value }) {
    return (
        <div className="mb-5">
            <label htmlFor={id} className="mb-2 block text-sm font-medium text-slate-300">{label}</label>
            <div className="relative">
                <Icon className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
                <input
                    id={id}
                    type={type}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    autoComplete={autoComplete}
                    disabled={disabled}
                    className={`min-h-11 w-full rounded-lg border bg-slate-700/50 py-3 pl-10 pr-4 text-white outline-none transition placeholder:text-slate-500 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 ${error ? 'border-red-500' : 'border-slate-600'}`}
                />
            </div>
            {error && <p className="mt-2 text-sm text-red-400">{error}</p>}
        </div>
    );
}