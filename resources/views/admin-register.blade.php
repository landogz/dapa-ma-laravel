<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DAPE-MA Admin Registration</title>
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
<body data-page="admin-register" class="min-h-screen bg-[#F9FAFB] text-[#0A0A0A]">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(5,84,152,0.2),_transparent_35%),radial-gradient(circle_at_bottom_left,_rgba(251,209,22,0.12),_transparent_28%)]"></div>
        <div class="relative mx-auto flex min-h-screen max-w-7xl items-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid w-full gap-8 lg:grid-cols-[0.95fr_1.05fr]">
                <section class="admin-shell-card p-6 sm:p-8">
                    <div class="mb-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#055498]">Initial Setup</p>
                        <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">Create the first super administrator</h1>
                        <p class="mt-2 text-sm text-slate-500">
                            This page is intended only for the first secure admin account. After a super admin exists, registration is locked by the API.
                        </p>
                    </div>

                    <form id="admin-register-form" class="space-y-5">
                        <div>
                            <label for="name" class="admin-auth-label">Full Name</label>
                            <input id="name" name="name" type="text" autocomplete="name" required class="admin-auth-input" placeholder="Administrator name">
                            <span id="name-error" class="admin-auth-error"></span>
                        </div>

                        <div>
                            <label for="email" class="admin-auth-label">Email Address</label>
                            <input id="email" name="email" type="email" autocomplete="email" required class="admin-auth-input" placeholder="admin@dape-ma.gov.ph">
                            <span id="email-error" class="admin-auth-error"></span>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="password" class="admin-auth-label">Password</label>
                                <div class="relative">
                                    <input id="password" name="password" type="password" autocomplete="new-password" required class="admin-auth-input pr-12" placeholder="Strong password">
                                    <button type="button" class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 transition hover:text-slate-700" data-password-toggle="password" aria-label="Toggle password visibility">
                                        <i class="fa-solid fa-eye-slash"></i>
                                    </button>
                                </div>
                                <span id="password-error" class="admin-auth-error"></span>
                            </div>

                            <div>
                                <label for="password_confirmation" class="admin-auth-label">Confirm Password</label>
                                <div class="relative">
                                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="admin-auth-input pr-12" placeholder="Repeat password">
                                    <button type="button" class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 transition hover:text-slate-700" data-password-toggle="password_confirmation" aria-label="Toggle password visibility">
                                        <i class="fa-solid fa-eye-slash"></i>
                                    </button>
                                </div>
                                <span id="password_confirmation-error" class="admin-auth-error"></span>
                            </div>
                        </div>

                        <button type="submit" class="admin-primary-button w-full">
                            Create Administrator
                        </button>
                    </form>

                    <a href="/admin/login" class="mt-5 inline-flex text-sm font-semibold text-[#055498] transition hover:text-[#123a60]">
                        Back to sign in
                    </a>
                </section>

                <section class="admin-shell-card hidden p-8 lg:block">
                    <div class="h-full rounded-[2rem] bg-[#123a60] p-8 text-slate-50">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#FBD116]">Security Notes</p>
                        <h2 class="mt-4 text-3xl font-bold tracking-tight">Protected onboarding for the control panel.</h2>
                        <ul class="mt-8 space-y-4 text-sm leading-7 text-slate-300">
                            <li>Initial registration is automatically restricted once a super admin account already exists.</li>
                            <li>All admin data requests are protected by Laravel Sanctum and role middleware on the backend API.</li>
                            <li>Role assignment remains under super administrator control from the dashboard after setup.</li>
                        </ul>
                    </div>
                </section>
            </div>
        </div>
    </div>
</body>
</html>
