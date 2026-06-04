<?php

use App\Models\User;
use App\Support\AdminPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::get('/admin', function () {
    return redirect('/admin/dashboard');
});

Route::get('/admin/login', function () {
    return view('admin-login', [
        'hasSuperAdmin' => User::query()->where('role', 'super_admin')->exists(),
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
