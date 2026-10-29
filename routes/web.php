<?php

use App\Http\Controllers\SharePageController;
use App\Models\User;
use App\Support\AdminPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $installed = is_file(storage_path('app/installed'))
        || filled(config('app.key'));

    if (! $installed) {
        return redirect('/install/');
    }

    return redirect('/admin/login');
});

Route::view('/chat', 'chat.tawk');

Route::get('/posts/{key}', [SharePageController::class, 'post'])
    ->where('key', '[0-9]+|[0-9a-fA-F-]{36}')
    ->name('share.posts');
Route::get('/events/{key}', [SharePageController::class, 'event'])
    ->where('key', '[0-9]+|[0-9a-fA-F-]{36}')
    ->name('share.events');
Route::get('/contests/{key}', [SharePageController::class, 'contest'])
    ->where('key', '[0-9]+|[0-9a-fA-F-]{36}')
    ->name('share.contests');
Route::get('/download/dape-ma.apk', [SharePageController::class, 'apk'])
    ->name('share.apk');
Route::get('/download/dape-ma.ipa', [SharePageController::class, 'ipa'])
    ->name('share.ipa');
Route::get('/download/ios', [SharePageController::class, 'iosInstall'])
    ->name('share.ios');
Route::get('/og-image/{type}/{key}.jpg', [SharePageController::class, 'ogImage'])
    ->whereIn('type', ['posts', 'events', 'contests'])
    ->where('key', '[0-9]+|[0-9a-fA-F-]{36}')
    ->name('share.og-image');

Route::get('/admin', function () {
    return redirect('/admin/dashboard');
});

Route::get('/admin/login', function () {
    $showDemoAccounts = filter_var(config('dape.show_demo_login'), FILTER_VALIDATE_BOOL);

    return view('admin-login', [
        'hasSuperAdmin' => User::query()->where('role', 'super_admin')->exists(),
        'demoAccounts' => $showDemoAccounts ? config('dape.demo_login_accounts', []) : [],
    ]);
});

Route::get('/reset-password', function () {
    return view('auth.reset-password', [
        'email' => request()->query('email', ''),
        'token' => request()->query('token', ''),
    ]);
})->name('password.reset');

Route::get('/admin/register', function () {
    if (User::query()->where('role', 'super_admin')->exists()) {
        return redirect('/admin/login');
    }

    return view('admin-register');
});

Route::get('/admin/dashboard', fn () => AdminPage::render('admin.dashboard.index', 'dashboard', 'Dashboard'));

Route::get('/admin/posts', fn () => AdminPage::render('admin.posts.index', 'posts', 'Posts'));

Route::get('/admin/rehab-centers', fn () => AdminPage::render('admin.rehab-centers.index', 'rehab-centers', 'Rehab Centers'));

Route::get('/admin/trainings', fn () => redirect('/admin/hope-events', 301));

Route::get('/admin/contests', fn () => AdminPage::render('admin.contests.index', 'contests', 'Contests'));

Route::get('/admin/iec-materials', fn () => AdminPage::render('admin.iec-materials.index', 'iec-materials', 'IEC Materials'));

Route::get('/admin/app-translations', fn () => AdminPage::render('admin.app-translations.index', 'app-translations', 'App Translations'));

Route::get('/admin/notifications', fn () => AdminPage::render('admin.notifications.index', 'notifications', 'In-app Notifications'));

Route::get('/admin/analytics', fn () => AdminPage::render('admin.analytics.index', 'analytics', 'Analytics'));

Route::get('/admin/users', fn () => AdminPage::render('admin.users.index', 'users', 'Users'));

Route::get('/admin/diary', fn () => AdminPage::render('admin.diary.index', 'diary', 'Journal'));

Route::get('/admin/care-toolkit', fn () => AdminPage::render('admin.care-toolkit.index', 'care-toolkit', 'Care Toolkit'));

Route::get('/admin/care-checks', fn () => AdminPage::render('admin.care-checks.index', 'care-checks', 'Care Checks'));

Route::get('/admin/care-support', fn () => AdminPage::render('admin.care-support.index', 'care-support', 'Care Support'));

Route::get('/admin/hope-directory', fn () => AdminPage::render('admin.hope-directory.index', 'hope-directory', 'Hope Directory'));

Route::get('/admin/hope-events', fn () => AdminPage::render('admin.hope-events.index', 'hope-events', 'Hope Events'));

Route::get('/admin/settings', fn () => AdminPage::render('admin.settings.index', 'settings', 'Settings'));

Route::get('/admin/lookups', fn () => AdminPage::render('admin.app-options.index', 'lookups', 'Lookups'));
Route::get('/admin/word-blocking', fn () => AdminPage::render('admin.word-blocking.index', 'word-blocking', 'Word Blocking'));
Route::get('/admin/audit-trail', fn () => AdminPage::render('admin.audit-trail.index', 'audit-trail', 'Audit Trail'));

Route::get('/admin/app-options', fn () => redirect('/admin/lookups', 301));

Route::get('/admin/profile', fn () => AdminPage::render('admin.profile.index', 'profile', 'Edit Profile'));
