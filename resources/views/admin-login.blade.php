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
<body data-page="admin-login" class="min-h-dvh bg-[#F3F6FA] text-[#0A0A0A]">
    <div class="relative flex min-h-dvh flex-col">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(5,84,152,0.16),_transparent_36%),radial-gradient(circle_at_bottom_right,_rgba(18,58,96,0.12),_transparent_30%)]"></div>
        <div class="pointer-events-none absolute inset-0 opacity-70 [background-image:linear-gradient(rgba(5,84,152,0.07)_1px,transparent_1px),linear-gradient(90deg,rgba(5,84,152,0.07)_1px,transparent_1px)] [background-size:32px_32px]"></div>

        <div class="relative flex min-h-dvh flex-col items-center px-6 py-6 sm:px-8 sm:py-8">
            <div class="my-auto w-full max-w-5xl">
            <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-[0_30px_80px_rgba(5,84,152,0.16)] lg:grid lg:grid-cols-2 lg:items-stretch">
                <section class="relative hidden min-h-full flex-col bg-gradient-to-br from-[#055498] to-[#123a60] p-8 text-white xl:p-10 lg:flex">
                    <div class="pointer-events-none absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(255,255,255,0.14)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.14)_1px,transparent_1px)] [background-size:32px_32px]"></div>
                    <div class="relative">
                        <div class="flex items-center gap-4">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-lg shadow-slate-950/15">
                                <img src="{{ asset('ddb.svg') }}" alt="" class="h-12 w-12 object-contain">
                            </div>
                            <p class="text-sm font-bold uppercase tracking-[0.24em] text-[#FBD116]">DAPE-MA Admin</p>
                        </div>
                        <h1 class="mt-8 max-w-md text-3xl font-bold leading-tight tracking-tight xl:text-4xl">
                            Clean, secure access for the admin panel.
                        </h1>
                        <p class="mt-3 max-w-md text-[14px] leading-normal text-white/80">
                            Manage content, rehab listings, notifications, and user access from one workspace.
                        </p>
                        <div class="mt-8 grid grid-cols-3 items-stretch gap-3">
                            <div class="flex h-full flex-col rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#FBD116]/20 text-[#FBD116]">
                                    <i class="fas fa-file-lines"></i>
                                </span>
                                <p class="mt-3 text-sm font-semibold text-[#FBD116]">Content</p>
                                <p class="mt-1 text-[15px] leading-snug text-white">Posts and publishing</p>
                            </div>
                            <div class="flex h-full flex-col rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#FBD116]/20 text-[#FBD116]">
                                    <i class="fas fa-hospital-user"></i>
                                </span>
                                <p class="mt-3 text-sm font-semibold text-[#FBD116]">Directory</p>
                                <p class="mt-1 text-[15px] leading-snug text-white">Rehab center listings</p>
                            </div>
                            <div class="flex h-full flex-col rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#FBD116]/20 text-[#FBD116]">
                                    <i class="fas fa-user-shield"></i>
                                </span>
                                <p class="mt-3 text-sm font-semibold text-[#FBD116]">Access</p>
                                <p class="mt-1 text-[15px] leading-snug text-white">Protected admin roles</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="flex min-h-full flex-col justify-start p-6 sm:p-8 lg:p-10">
                    <div class="mb-6 flex items-center gap-3 lg:hidden">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                            <img src="{{ asset('ddb.svg') }}" alt="" class="h-10 w-10 object-contain">
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#055498]">DAPE-MA Admin</p>
                            <p class="truncate text-sm text-slate-600">Dangerous Drugs Board</p>
                        </div>
                    </div>

                    <div id="admin-login-view">
                    <div class="mb-6">
                        <h2 class="text-3xl font-bold tracking-tight text-slate-950">Welcome back</h2>
                        <p class="mt-4 text-sm leading-normal text-slate-600">Use your admin account to continue.</p>
                    </div>
                    <p id="login-reset-success" class="mb-4 hidden text-sm font-medium leading-5 text-emerald-700" role="status"></p>

                    <form id="admin-login-form" class="space-y-5">
                        <div>
                            <label for="email" class="admin-login-label">Email Address</label>
                            <input id="email" name="email" type="email" autocomplete="email" required class="admin-auth-input admin-login-field" placeholder="admin@dape-ma.gov.ph">
                            <span id="email-error" class="admin-auth-error" role="alert"></span>
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <label for="password" class="admin-login-label mb-0">Password</label>
                                <button type="button" data-forgot-password class="text-sm font-semibold text-[#055498] hover:text-[#123a60] focus:outline-none focus:ring-2 focus:ring-[#055498] focus:ring-offset-2">Forgot password?</button>
                            </div>
                            <div class="relative">
                                <input id="password" name="password" type="password" autocomplete="current-password" required class="admin-auth-input admin-login-field pr-12" placeholder="Enter your password">
                                <button type="button" class="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center px-3 text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#055498] focus:ring-inset" data-password-toggle="password">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <span id="password-error" class="admin-auth-error" role="alert"></span>
                        </div>

                        <button type="submit" class="admin-primary-button admin-login-submit w-full gap-2 rounded-xl" data-busy-spinner="true">
                            Sign In
                        </button>
                    </form>

                    <p class="mt-5 flex items-center justify-center gap-2 text-center text-sm font-medium leading-5 text-slate-600">
                        <i class="fas fa-lock text-xs text-[#055498]" aria-hidden="true"></i>
                        <span>Restricted admin area. Authorized personnel only.</span>
                    </p>

                    @unless($hasSuperAdmin)
                        <div class="mt-5 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 p-4">
                            <p class="text-sm font-semibold text-[#123a60]">First-time setup</p>
                            <a href="/admin/register" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-[#055498] transition hover:text-[#123a60] focus:outline-none focus:ring-2 focus:ring-[#055498] focus:ring-offset-2">
                                Create the first administrator
                            </a>
                        </div>
                    @endunless

                    @if (!empty($demoAccounts))
                        <details class="admin-login-demo-accounts mt-4">
                            <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-xl px-1 text-sm font-medium text-slate-600 focus:outline-none focus:ring-2 focus:ring-[#055498]">
                                <span>Show demo accounts</span>
                                <span class="rounded-full bg-[#FBD116] px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-[#123a60]">Demo</span>
                            </summary>
                            <p class="admin-login-demo-hint">Tap an account to fill the form and sign in.</p>
                            <div class="admin-login-demo-grid">
                                @foreach ($demoAccounts as $account)
                                    <button
                                        type="button"
                                        class="admin-login-demo-account"
                                        data-demo-login
                                        data-demo-email="{{ $account['email'] }}"
                                        data-demo-password="{{ $account['password'] }}"
                                        data-demo-label="{{ $account['label'] }}"
                                    >
                                        <span class="admin-login-demo-account-label">{{ $account['label'] }}</span>
                                        <span class="admin-login-demo-account-email">{{ $account['email'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </details>
                    @endif
                    </div>

                    <div id="admin-forgot-view" class="hidden">
                        <button type="button" data-forgot-back class="mb-5 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-[#055498] hover:text-[#123a60] focus:outline-none focus:ring-2 focus:ring-[#055498] focus:ring-offset-2">
                            <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
                            <span data-forgot-back-label>Back to Login</span>
                        </button>

                        <div data-forgot-step="email">
                            <h2 class="text-3xl font-bold tracking-tight text-slate-950">Forgot password?</h2>
                            <p class="mt-4 text-sm leading-normal text-slate-600">Enter your email. We will send a 6-digit code — check that inbox before you can set a new password.</p>
                            <form id="forgot-email-form" class="mt-6 space-y-5" novalidate>
                                <div>
                                    <label for="forgot-email" class="admin-login-label">Email Address</label>
                                    <input id="forgot-email" name="email" type="email" autocomplete="email" required class="admin-auth-input admin-login-field" placeholder="Enter your email">
                                    <span id="forgot-email-error" class="admin-auth-error" role="alert"></span>
                                </div>
                                <button type="submit" class="admin-primary-button admin-login-submit w-full gap-2 rounded-xl" data-busy-spinner="true">
                                    Send reset code
                                </button>
                            </form>
                        </div>

                        <div data-forgot-step="code" class="hidden">
                            <h2 class="text-3xl font-bold tracking-tight text-slate-950">Check your email</h2>
                            <p class="mt-4 text-sm leading-normal text-[#055498]" data-forgot-sent-to></p>
                            <p class="mt-2 text-sm leading-normal text-slate-600">Check your email for a 6-digit code, then continue here.</p>
                            <form id="forgot-code-form" class="mt-6 space-y-5" novalidate>
                                <div class="grid grid-cols-6 gap-1.5 sm:gap-2" data-reset-code>
                                    @for ($digit = 0; $digit < 6; $digit++)
                                        <input
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="one-time-code"
                                            maxlength="1"
                                            aria-label="Digit {{ $digit + 1 }}"
                                            class="admin-reset-code"
                                            data-reset-digit
                                        >
                                    @endfor
                                </div>
                                <span id="forgot-code-error" class="admin-auth-error" role="alert"></span>
                                <button type="submit" class="admin-primary-button admin-login-submit w-full gap-2 rounded-xl" data-busy-spinner="true" disabled>
                                    Verify Code
                                </button>
                            </form>
                            <p class="mt-4 text-center text-sm text-slate-600">
                                Didn't receive a code?
                                <span data-resend-wait class="font-semibold text-[#055498]">Resend in 00:45</span>
                                <button type="button" data-resend-code class="hidden min-h-11 font-semibold text-[#055498] hover:text-[#123a60] focus:outline-none focus:ring-2 focus:ring-[#055498] focus:ring-offset-2">Resend</button>
                            </p>
                        </div>

                        <div data-forgot-step="password" class="hidden">
                            <h2 class="text-3xl font-bold tracking-tight text-slate-950">Reset password</h2>
                            <p class="mt-4 text-sm leading-normal text-slate-600">Enter a new password for your account.</p>
                            <p class="mt-2 text-center text-sm font-semibold text-slate-900" data-forgot-email-label></p>
                            <form id="forgot-password-form" class="mt-6 space-y-5" novalidate>
                                <div>
                                    <label for="forgot-new-password" class="admin-login-label">New password</label>
                                    <div class="relative">
                                        <input id="forgot-new-password" name="password" type="password" autocomplete="new-password" required minlength="8" class="admin-auth-input admin-login-field pr-12" placeholder="New password">
                                        <button type="button" class="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center px-3 text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#055498] focus:ring-inset" data-password-toggle="forgot-new-password">
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <span id="forgot-password-error" class="admin-auth-error" role="alert"></span>
                                </div>
                                <div>
                                    <label for="forgot-confirm-password" class="admin-login-label">Confirm new password</label>
                                    <div class="relative">
                                        <input id="forgot-confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" class="admin-auth-input admin-login-field pr-12" placeholder="Confirm new password">
                                        <button type="button" class="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center px-3 text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#055498] focus:ring-inset" data-password-toggle="forgot-confirm-password">
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <span id="forgot-confirm-error" class="admin-auth-error" role="alert"></span>
                                </div>
                                <button type="submit" class="admin-primary-button admin-login-submit w-full gap-2 rounded-xl" data-busy-spinner="true">
                                    Save new password
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="mt-5 text-center text-xs leading-5 text-slate-500">
                <p>© {{ date('Y') }} Dangerous Drugs Board · Need help? Contact IT support.</p>
                <p class="mt-1">
                    <a href="{{ app(\App\Services\SharePageService::class)->apkDownloadUrl() }}" class="text-slate-500 underline-offset-2 hover:text-[#055498] hover:underline focus:outline-none focus:ring-2 focus:ring-[#055498] focus:ring-offset-2">Android app</a>
                    <span aria-hidden="true"> · </span>
                    <a href="{{ route('share.ios') }}" class="text-slate-500 underline-offset-2 hover:text-[#055498] hover:underline focus:outline-none focus:ring-2 focus:ring-[#055498] focus:ring-offset-2">iPhone app</a>
                </p>
            </footer>
            </div>
        </div>
    </div>
</body>
</html>
