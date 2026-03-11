<?php

use App\Http\Controllers\API\Admin\AdminNotificationController;
use App\Http\Controllers\API\Admin\AnalyticsAdminController;
use App\Http\Controllers\API\Admin\CategoryAdminController;
use App\Http\Controllers\API\Admin\NotificationAdminController;
use App\Http\Controllers\API\Admin\PostAdminController;
use App\Http\Controllers\API\Admin\RehabCenterAdminController;
use App\Http\Controllers\API\Admin\UserAdminController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BookmarkController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\RehabCenterController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DAPE-MA API Routes  —  /api/v1/
|--------------------------------------------------------------------------
| Standard JSON envelope:
|   Success: { "status": true,  "message": "...", "data": {...} }
|   Error:   { "status": false, "message": "...", "errors": {...} }
|--------------------------------------------------------------------------
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {

        // ── Health ────────────────────────────────────────────────────────
        Route::get('/health', function (Request $request) {
            return response()->json([
                'status'  => true,
                'message' => 'DAPE-MA API is healthy.',
                'data'    => [
                    'environment' => app()->environment(),
                    'version'     => config('app.version'),
                ],
            ]);
        })->name('health');

        // ── Auth (public) ─────────────────────────────────────────────────
        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('/register', [AuthController::class, 'register'])->name('register');
            Route::post('/login', [AuthController::class, 'login'])->name('login');
            Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        });

        // ── Public content ────────────────────────────────────────────────
        Route::prefix('posts')->name('posts.')->group(function (): void {
            Route::get('/',    [PostController::class, 'index'])->name('index');
            Route::get('/{id}', [PostController::class, 'show'])->name('show');
        });

        // ── Public search ────────────────────────────────────────────────
        Route::get('/search', [SearchController::class, 'index'])->name('search.index');

        // ── Public analytics events (mobile, unauthenticated) ───────────
        Route::post('/analytics/events', [\App\Http\Controllers\API\AnalyticsEventController::class, 'store'])
            ->name('analytics.events.store');

        // ── Public rehab centers directory ───────────────────────────────
        Route::get('/rehab-centers', [RehabCenterController::class, 'index'])
            ->name('rehab-centers.index');

        // ── Authenticated ─────────────────────────────────────────────────
        Route::middleware('auth:sanctum')->group(function (): void {

            Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
            Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
            Route::put('/auth/password', [AuthController::class, 'changePassword'])->name('auth.password.update');

            // Basic user list (any authenticated user)
            Route::get('/users', [UserController::class, 'index'])->name('users.index');

            // ── Bookmarks ────────────────────────────────────────────────
            Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
            Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');

            // ── Reviews ─────────────────────────────────────────────────
            Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');

            // ── Admin ─────────────────────────────────────────────────────
            Route::prefix('admin')->name('admin.')->group(function (): void {

                // Super Admin only
                Route::middleware('role:super_admin')->group(function (): void {
                    Route::get('/users',                     [UserAdminController::class, 'index'])->name('users.index');
                    Route::post('/users',                    [UserAdminController::class, 'store'])->name('users.store');
                    Route::put('/users/{user}',              [UserAdminController::class, 'update'])->name('users.update');
                    Route::put('/users/{user}/role',         [UserAdminController::class, 'updateRole'])->name('users.role');
                    Route::delete('/users/{user}',           [UserAdminController::class, 'destroy'])->name('users.destroy');
                    Route::put('/posts/{post}/archive',      [PostAdminController::class, 'archive'])->name('posts.archive');
                    Route::delete('/posts/{post}',           [PostAdminController::class, 'destroy'])->name('posts.destroy');
                });

                // Editors, publishers, and super admin can review the content queue
                Route::middleware('role:editor,publisher,super_admin')->group(function (): void {
                    Route::get('/categories',         [CategoryAdminController::class, 'index'])->name('categories.index');
                    Route::get('/posts/options',      [PostAdminController::class, 'options'])->name('posts.options');
                    Route::get('/posts',              [PostAdminController::class, 'index'])->name('posts.index');
                    Route::get('/posts/{post}',       [PostAdminController::class, 'show'])->name('posts.show');
                });

                // Editor — draft creation, editing, and submission
                Route::middleware('role:editor')->group(function (): void {
                    Route::post('/posts',             [PostAdminController::class, 'store'])->name('posts.store');
                    Route::put('/posts/{post}',       [PostAdminController::class, 'update'])->name('posts.update');
                    Route::put('/posts/{post}/submit', [PostAdminController::class, 'submit'])->name('posts.submit');
                });

                // Publisher — review and scheduling
                Route::middleware('role:publisher')->group(function (): void {
                    Route::put('/posts/{post}/reject',   [PostAdminController::class, 'reject'])->name('posts.reject');
                    Route::put('/posts/{post}/schedule', [PostAdminController::class, 'schedule'])->name('posts.schedule');
                    Route::put('/posts/{post}/publish',  [PostAdminController::class, 'publish'])->name('posts.publish');
                });

                // Publisher + Super Admin — notifications
                Route::middleware('role:publisher,super_admin')->group(function (): void {
                    Route::get('/notifications',       [NotificationAdminController::class, 'index'])->name('notifications.index');
                    Route::post('/notifications/send', [NotificationAdminController::class, 'send'])->name('notifications.send');
                });

                // Admin in-app notification inbox (all admin roles)
                Route::middleware('role:super_admin,editor,publisher,analytics_viewer')->group(function (): void {
                    Route::get('/inbox',                 [AdminNotificationController::class, 'index'])->name('admin-inbox.index');
                    Route::get('/inbox/summary',         [AdminNotificationController::class, 'summary'])->name('admin-inbox.summary');
                    Route::post('/inbox/read-all',       [AdminNotificationController::class, 'markAllAsRead'])->name('admin-inbox.read-all');
                    Route::post('/inbox/{notification}/read', [AdminNotificationController::class, 'markAsRead'])->name('admin-inbox.read');
                });

                // Any admin role — rehab centers CRUD
                Route::middleware('role:super_admin,editor,publisher,analytics_viewer')
                    ->group(function (): void {
                        Route::get('/rehab-centers',              [RehabCenterAdminController::class, 'index'])->name('rehab-centers.index');
                        Route::post('/rehab-centers',             [RehabCenterAdminController::class, 'store'])->name('rehab-centers.store');
                        Route::get('/rehab-centers/{rehabCenter}', [RehabCenterAdminController::class, 'show'])->name('rehab-centers.show');
                        Route::put('/rehab-centers/{rehabCenter}', [RehabCenterAdminController::class, 'update'])->name('rehab-centers.update');
                        Route::delete('/rehab-centers/{rehabCenter}', [RehabCenterAdminController::class, 'destroy'])->name('rehab-centers.destroy');
                    });

                // Analytics Viewer + Super Admin
                Route::middleware('role:analytics_viewer,super_admin')->group(function (): void {
                    Route::get('/analytics',        [AnalyticsAdminController::class, 'index'])->name('analytics.index');
                    Route::get('/analytics/export', [AnalyticsAdminController::class, 'export'])->name('analytics.export');
                });
            });
        });
    });
