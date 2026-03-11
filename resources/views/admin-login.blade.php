<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DAPE-MA Admin Login</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('ddb.svg') }}">
    <link rel="shortcut icon" href="{{ asset('ddb.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('ddb.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/gotham-fonts@1.0.3/css/gotham-rounded.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-page="admin-login" class="min-h-screen bg-[#F9FAFB] text-[#0A0A0A]">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(5,84,152,0.18),_transparent_35%),radial-gradient(circle_at_bottom_right,_rgba(18,58,96,0.14),_transparent_28%)]"></div>
        <div class="relative mx-auto flex min-h-screen max-w-7xl items-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid w-full gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:gap-8">
                <section class="relative hidden overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#055498] to-[#123a60] p-9 pt-12 text-white shadow-[0_30px_80px_rgba(5,84,152,0.22)] lg:flex lg:min-h-[620px] lg:flex-col lg:justify-between">
                    <div class="absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(255,255,255,0.12)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.12)_1px,transparent_1px)] [background-size:32px_32px]"></div>
                    <div class="relative">
                        <div class="inline-flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-lg shadow-slate-950/15">
                            <img src="{{ asset('ddb.svg') }}" alt="DDB logo" class="h-11 w-11 object-contain">
                        </div>
                        <p class="mt-7 text-sm font-semibold uppercase tracking-[0.24em] text-[#FBD116]">DAPE-MA Admin</p>
                        <h1 class="mt-4 max-w-lg text-4xl font-bold tracking-tight">
                            Clean, secure access for the admin panel.
                        </h1>
                        <p class="mt-4 max-w-md text-sm leading-7 text-slate-100/90">
                            Manage content, rehab listings, notifications, and user access from one workspace.
                        </p>
                    </div>

                    <div class="relative grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-[#FBD116]/30 bg-white/10 p-4 backdrop-blur">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#FBD116]/20 text-[#FBD116]">
                                <i class="fas fa-file-lines"></i>
                            </span>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-[#FBD116]">Content</p>
                            <p class="mt-2 text-sm text-white">Posts and publishing</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-300/25 bg-white/10 p-4 backdrop-blur">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-300/15 text-emerald-200">
                                <i class="fas fa-hospital-user"></i>
                            </span>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-emerald-200">Directory</p>
                            <p class="mt-2 text-sm text-white">Rehab center listings</p>
                        </div>
                        <div class="rounded-2xl border border-[#f97316]/30 bg-white/10 p-4 backdrop-blur">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#f97316]/15 text-[#fed7aa]">
                                <i class="fas fa-user-shield"></i>
                            </span>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-[#fed7aa]">Access</p>
                            <p class="mt-2 text-sm text-white">Protected admin roles</p>
                        </div>
                    </div>
                </section>

                <section class="admin-shell-card p-6 shadow-[0_24px_70px_rgba(15,23,42,0.08)] sm:p-8 lg:p-10">
                    <div class="mb-8">
                        <div class="inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-sm lg:hidden">
                            <img src="{{ asset('ddb.svg') }}" alt="DDB logo" class="h-9 w-9 object-contain">
                        </div>
                        <p class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] text-[#055498]">Administrator Access</p>
                        <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">Sign In</h2>
                        <p class="mt-2 text-sm text-slate-500">
                            Use your admin account to continue.
                        </p>
                    </div>

                    <form id="admin-login-form" class="space-y-5">
                        <div>
                            <label for="email" class="admin-auth-label">Email Address</label>
                            <input id="email" name="email" type="email" autocomplete="email" required class="admin-auth-input" placeholder="admin@dape-ma.gov.ph">
                            <span id="email-error" class="admin-auth-error"></span>
                        </div>

                        <div>
                            <label for="password" class="admin-auth-label">Password</label>
                            <div class="relative">
                                <input id="password" name="password" type="password" autocomplete="current-password" required class="admin-auth-input pr-12" placeholder="Enter your password">
                                <button type="button" class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 transition hover:text-slate-700" data-password-toggle="password" aria-label="Toggle password visibility">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </button>
                            </div>
                            <span id="password-error" class="admin-auth-error"></span>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <a href="#" class="font-medium text-[#055498] hover:text-[#123a60]">Forgot password?</a>
                        </div>

                        <button type="submit" class="admin-primary-button w-full rounded-xl">
                            Sign In
                        </button>
                    </form>

                    <div class="mt-5 flex items-center gap-2 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                        <i class="fas fa-lock text-[#b45309]"></i>
                        <span>Restricted admin area. Authorized personnel only.</span>
                    </div>

                    @unless($hasSuperAdmin)
                        <div class="mt-6 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 p-4">
                            <p class="text-sm font-semibold text-[#123a60]">First-time setup</p>
                            <a href="/admin/register" class="mt-3 inline-flex text-sm font-semibold text-[#055498] transition hover:text-[#123a60]">
                                Create the first administrator
                            </a>
                        </div>
                    @endunless
                </section>
            </div>
        </div>
    </div>
</body>
</html>
