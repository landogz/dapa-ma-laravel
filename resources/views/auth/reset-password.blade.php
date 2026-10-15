<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password — DAPE-MA</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('ddb.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    data-page="reset-password"
    class="min-h-screen bg-[#F9FAFB] text-[#0A0A0A]"
    data-email="{{ e($email) }}"
    data-token="{{ e($token) }}"
>
    <div class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-[#055498] via-[#123a60] to-[#055498]"></div>
        <div class="relative mx-auto flex min-h-screen max-w-lg items-center px-4 py-10 sm:px-6">
            <section class="w-full rounded-3xl bg-white p-6 shadow-2xl shadow-slate-950/20 sm:p-8">
                <div class="flex flex-col items-center text-center">
                    <img src="{{ asset('ddb.svg') }}" alt="DDB logo" class="h-16 w-16 object-contain">
                    <p class="mt-3 text-sm font-bold tracking-wide text-[#055498]">DAPE-MA</p>
                    <h1 class="mt-4 text-2xl font-bold text-[#123a60]">Reset password</h1>
                    <p class="mt-2 text-sm text-slate-500">Choose a new password for your account.</p>
                </div>

                <form id="reset-password-form" class="mt-8 space-y-4" novalidate>
                    <div>
                        <label for="reset-email" class="mb-1.5 block text-left text-sm font-semibold text-[#123a60]">Email</label>
                        <input
                            id="reset-email"
                            type="email"
                            name="email"
                            value="{{ e($email) }}"
                            autocomplete="username"
                            readonly
                            disabled
                            aria-disabled="true"
                            class="min-h-11 w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-600"
                        >
                    </div>
                    <div>
                        <label for="reset-password" class="mb-1.5 block text-left text-sm font-semibold text-[#123a60]">New password</label>
                        <input
                            id="reset-password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            minlength="8"
                            class="min-h-11 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none ring-[#055498]/30 focus:ring-2"
                            required
                        >
                    </div>
                    <div>
                        <label for="reset-password-confirmation" class="mb-1.5 block text-left text-sm font-semibold text-[#123a60]">Confirm password</label>
                        <input
                            id="reset-password-confirmation"
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            minlength="8"
                            class="min-h-11 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none ring-[#055498]/30 focus:ring-2"
                            required
                        >
                    </div>
                    <p id="reset-password-message" class="hidden text-sm" role="status"></p>
                    <button
                        id="reset-password-submit"
                        type="submit"
                        class="flex min-h-11 w-full items-center justify-center rounded-xl bg-gradient-to-r from-[#055498] to-[#123a60] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-95 disabled:opacity-60"
                    >
                        Save new password
                    </button>
                </form>

                <p class="mt-6 text-center text-sm">
                    <a href="/admin/login" class="font-semibold text-[#055498] hover:text-[#123a60]">Back to Login</a>
                </p>
            </section>
        </div>
    </div>
</body>
</html>
