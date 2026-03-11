<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

function renderAdminPage(string $view, string $activePage, string $headerTitle)
{
    return view($view, [
        'pageTitle' => "DAPE-MA Admin | {$headerTitle}",
        'bodyPage' => 'admin-' . $activePage,
        'activePage' => $activePage,
        'headerTitle' => $headerTitle,
    ]);
}

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

Route::get('/admin/dashboard', function () {
    return renderAdminPage('admin.dashboard.index', 'dashboard', 'Dashboard');
});

Route::get('/admin/posts', function () {
    return renderAdminPage('admin.posts.index', 'posts', 'Posts');
});

Route::get('/admin/rehab-centers', function () {
    return renderAdminPage('admin.rehab-centers.index', 'rehab-centers', 'Rehab Centers');
});

Route::get('/admin/notifications', function () {
    return renderAdminPage('admin.notifications.index', 'notifications', 'Notifications');
});

Route::get('/admin/analytics', function () {
    return renderAdminPage('admin.analytics.index', 'analytics', 'Analytics');
});

Route::get('/admin/users', function () {
    return renderAdminPage('admin.users.index', 'users', 'Users');
});
