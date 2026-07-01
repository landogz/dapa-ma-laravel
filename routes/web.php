<?php

use App\Models\User;
use App\Support\AdminPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::view('/chat', 'chat.botpress');

Route::get('/posts/{id}', function (int $id) {
    return view('posts.show', ['postId' => $id]);
})->whereNumber('id');

Route::get('/admin', function () {
    return redirect('/admin/dashboard');
});

Route::get('/admin/login', function () {
    $showDemoAccounts = config('dape.show_demo_login') || app()->environment('local');

    return view('admin-login', [
        'hasSuperAdmin' => User::query()->where('role', 'super_admin')->exists(),
        'demoAccounts' => $showDemoAccounts ? config('dape.demo_login_accounts', []) : [],
    ]);
});

Route::get('/admin/register', function () {
    if (User::query()->where('role', 'super_admin')->exists()) {
        return redirect('/admin/login');
    }

    return view('admin-register');
});

Route::get('/admin/dashboard', fn () => AdminPage::render('admin.dashboard.index', 'dashboard', 'Dashboard'));

Route::get('/admin/posts', fn () => AdminPage::render('admin.posts.index', 'posts', 'Posts'));

Route::get('/admin/rehab-centers', fn () => AdminPage::render('admin.rehab-centers.index', 'rehab-centers', 'Rehab Centers'));

Route::get('/admin/notifications', fn () => AdminPage::render('admin.notifications.index', 'notifications', 'Notifications'));

Route::get('/admin/analytics', fn () => AdminPage::render('admin.analytics.index', 'analytics', 'Analytics'));

Route::get('/admin/users', fn () => AdminPage::render('admin.users.index', 'users', 'Users'));

Route::get('/admin/profile', fn () => AdminPage::render('admin.profile.index', 'profile', 'Edit Profile'));
