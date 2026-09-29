<?php

use App\Http\Controllers\API\Admin\AdminNotificationController;
use App\Http\Controllers\API\Admin\AnalyticsAdminController;
use App\Http\Controllers\API\Admin\CategoryAdminController;
use App\Http\Controllers\API\Admin\DiaryAdminController;
use App\Http\Controllers\API\Admin\NotificationAdminController;
use App\Http\Controllers\API\Admin\PostAdminController;
use App\Http\Controllers\API\Admin\RehabCenterAdminController;
use App\Http\Controllers\API\Admin\TrainingAdminController;
use App\Http\Controllers\API\Admin\ContestAdminController;
use App\Http\Controllers\API\Admin\IecMaterialAdminController;
use App\Http\Controllers\API\Admin\AppTranslationAdminController;
use App\Http\Controllers\API\Admin\UserAdminController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BookmarkController;
use App\Http\Controllers\API\DiaryController;
use App\Http\Controllers\API\PostEngagementController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\ProfileStatsController;
use App\Http\Controllers\API\RehabCenterController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\TrainingController;
use App\Http\Controllers\API\ContestController;
use App\Http\Controllers\API\IecMaterialController;
use App\Http\Controllers\API\AppTranslationController;
use App\Http\Controllers\API\LegalPageController;
use App\Http\Controllers\API\KidListoQuoteController;
use App\Http\Controllers\API\MoodCheckinController;
use App\Http\Controllers\API\Admin\LegalPageAdminController;
use App\Http\Controllers\API\Admin\KidListoQuoteAdminController;
use App\Http\Controllers\API\CareToolkitQuestionController;
use App\Http\Controllers\API\Admin\CareToolkitQuestionAdminController;
use App\Http\Controllers\API\CareSupportResourceController;
use App\Http\Controllers\API\Admin\CareSupportResourceAdminController;
use App\Http\Controllers\API\HopeDirectoryController;
use App\Http\Controllers\API\HopeEventController;
use App\Http\Controllers\API\Admin\HopeDirectoryAdminController;
use App\Http\Controllers\API\Admin\HopeEventAdminController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\UserNotificationController;
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
            Route::get('/', [PostController::class, 'index'])->name('index');
            Route::get('/{id}/comments', [PostEngagementController::class, 'comments'])
                ->whereNumber('id')
                ->name('comments.index');
            Route::get('/{id}/reactions', [PostEngagementController::class, 'reactions'])
                ->whereNumber('id')
                ->name('reactions.index');
            Route::get('/{id}/reviews', [ReviewController::class, 'indexForPost'])
                ->whereNumber('id')
                ->name('reviews.index');
            Route::get('/{id}', [PostController::class, 'show'])
                ->whereNumber('id')
                ->name('show');
        });

        // ── Public search ────────────────────────────────────────────────
        Route::get('/search', [SearchController::class, 'index'])->name('search.index');

        // ── Public analytics events (mobile, unauthenticated) ───────────
        Route::post('/analytics/events', [\App\Http\Controllers\API\AnalyticsEventController::class, 'store'])
            ->name('analytics.events.store');

        // ── Public rehab centers directory ───────────────────────────────
        Route::get('/rehab-centers', [RehabCenterController::class, 'index'])
            ->name('rehab-centers.index');

        // ── Public Hope directory & events ───────────────────────────────
        Route::get('/hope-directory', [HopeDirectoryController::class, 'index'])
            ->name('hope-directory.index');
        Route::get('/hope-events', [HopeEventController::class, 'index'])
            ->name('hope-events.index');
        Route::get('/hope-events/{id}', [HopeEventController::class, 'show'])
            ->whereNumber('id')
            ->name('hope-events.show');

        // ── Public DDB trainings directory ───────────────────────────────
        Route::get('/trainings', [TrainingController::class, 'index'])
            ->name('trainings.index');
        Route::get('/trainings/{training}', [TrainingController::class, 'show'])
            ->whereNumber('training')
            ->name('trainings.show');

        // ── Public contests (song | poster | video via category) ─────────
        Route::get('/contests', [ContestController::class, 'index'])
            ->name('contests.index');
        Route::get('/contests/{contest}', [ContestController::class, 'show'])
            ->whereNumber('contest')
            ->name('contests.show');

        Route::get('/iec-materials', [IecMaterialController::class, 'index'])
            ->name('iec-materials.index');
        Route::get('/iec-materials/{iecMaterial}', [IecMaterialController::class, 'show'])
            ->whereNumber('iecMaterial')
            ->name('iec-materials.show');

        // ── Public mobile app translations (EN / TL) ─────────────────────
        Route::get('/translations', [AppTranslationController::class, 'index'])
            ->name('translations.index');
        Route::get('/translations/{locale}', [AppTranslationController::class, 'show'])
            ->where('locale', 'en|tl|fil')
            ->name('translations.show');

        // ── Public legal pages (Privacy Policy / Terms of Use) ───────────
        Route::get('/legal-pages', [LegalPageController::class, 'index'])
            ->name('legal-pages.index');
        Route::get('/legal-pages/{slug}', [LegalPageController::class, 'show'])
            ->where('slug', 'privacy_policy|terms_of_use')
            ->name('legal-pages.show');

        // ── Public Kid Listo motivational quotes ─────────────────────────
        Route::get('/kid-listo/random', [KidListoQuoteController::class, 'random'])
            ->name('kid-listo.random');
        // Backward-compatible alias for older mobile builds.
        Route::get('/daily-verse/today', [KidListoQuoteController::class, 'random'])
            ->name('daily-verse.today');

        // ── Public DAPE Care toolkit questions ───────────────────────────
        Route::get('/care-toolkit/questions', [CareToolkitQuestionController::class, 'index'])
            ->name('care-toolkit.questions');

        Route::get('/care-support/resources', [CareSupportResourceController::class, 'index'])
            ->name('care-support.resources');

        // ── Authenticated ─────────────────────────────────────────────────
        Route::middleware('auth:sanctum')->group(function (): void {

            Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
            Route::get('/me/profile', [ProfileStatsController::class, 'show'])->name('me.profile');
            Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
            Route::post('/auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update.post');
            Route::put('/auth/onboarding', [AuthController::class, 'updateOnboarding'])->name('auth.onboarding.update');
            Route::put('/auth/password', [AuthController::class, 'changePassword'])->name('auth.password.update');

            // Basic user list (any authenticated user)
            Route::get('/users', [UserController::class, 'index'])->name('users.index');

            // ── Bookmarks ────────────────────────────────────────────────
            Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
            Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');

            // ── My Journal ───────────────────────────────────────────────
            Route::get('/diary-entries/today', [DiaryController::class, 'today'])->name('diary-entries.today');
            Route::apiResource('diary-entries', DiaryController::class)->except(['create', 'edit']);
            // Multipart-friendly update (PHP does not reliably parse files on PUT).
            Route::post('/diary-entries/{id}', [DiaryController::class, 'update'])
                ->whereNumber('id')
                ->name('diary-entries.update.post');

            // ── Mood check-ins (DAPE Care) ───────────────────────────────
            Route::get('/mood-checkins/today', [MoodCheckinController::class, 'today'])->name('mood-checkins.today');
            Route::get('/mood-checkins', [MoodCheckinController::class, 'index'])->name('mood-checkins.index');
            Route::post('/mood-checkins', [MoodCheckinController::class, 'store'])->name('mood-checkins.store');

            // ── Contest submissions (song | poster | video) ───────────────
            Route::post('/contests/{contest}/entries', [ContestController::class, 'submit'])
                ->whereNumber('contest')
                ->name('contests.entries.store');
            Route::get('/contests/{contest}/my-entry', [ContestController::class, 'myEntry'])
                ->whereNumber('contest')
                ->name('contests.my-entry');

            // ── Post engagement (likes & comments) ───────────────────────
            Route::post('/posts/{id}/like', [PostEngagementController::class, 'toggleLike'])
                ->whereNumber('id')
                ->name('posts.like');
            Route::post('/posts/{id}/comments', [PostEngagementController::class, 'storeComment'])
                ->whereNumber('id')
                ->name('posts.comments.store');
            Route::put('/posts/{id}/comments/{commentId}', [PostEngagementController::class, 'updateComment'])
                ->whereNumber('id')
                ->whereNumber('commentId')
                ->name('posts.comments.update');
            Route::delete('/posts/{id}/comments/{commentId}', [PostEngagementController::class, 'destroyComment'])
                ->whereNumber('id')
                ->whereNumber('commentId')
                ->name('posts.comments.destroy');

            // ── Reviews ─────────────────────────────────────────────────
            Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');

            // ── User notification inbox (mobile + any authenticated user) ─
            Route::prefix('notifications')->name('notifications.')->group(function (): void {
                Route::get('/', [UserNotificationController::class, 'index'])->name('index');
                Route::get('/summary', [UserNotificationController::class, 'summary'])->name('summary');
                Route::post('/read-all', [UserNotificationController::class, 'markAllAsRead'])->name('read-all');
                Route::post('/{userNotification}/read', [UserNotificationController::class, 'markAsRead'])
                    ->whereNumber('userNotification')
                    ->name('read');
            });

            // ── Admin ─────────────────────────────────────────────────────
            Route::prefix('admin')->name('admin.')->group(function (): void {

                // Super Admin only
                Route::middleware('role:super_admin')->group(function (): void {
                    Route::get('/users',                     [UserAdminController::class, 'index'])->name('users.index');
                    Route::post('/users',                    [UserAdminController::class, 'store'])->name('users.store');
                    Route::put('/users/{user}',              [UserAdminController::class, 'update'])->name('users.update');
                    Route::put('/users/{user}/role',         [UserAdminController::class, 'updateRole'])->name('users.role');
                    Route::delete('/users/{user}',           [UserAdminController::class, 'destroy'])->name('users.destroy');
                    Route::get('/diary-entries',             [DiaryAdminController::class, 'index'])->name('diary-entries.index');
                    Route::get('/diary-users',               [DiaryAdminController::class, 'users'])->name('diary-users.index');
                    Route::get('/diary-entries/{id}',       [DiaryAdminController::class, 'show'])->whereNumber('id')->name('diary-entries.show');
                    Route::delete('/diary-entries/{id}',    [DiaryAdminController::class, 'destroy'])->whereNumber('id')->name('diary-entries.destroy');
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

                // Editor + Super Admin — draft creation, editing, and submission
                Route::middleware('role:editor,super_admin')->group(function (): void {
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
                    Route::get('/notifications', [NotificationAdminController::class, 'index'])->name('notifications.index');
                    Route::get('/notifications/audience-count', [NotificationAdminController::class, 'audienceCount'])->name('notifications.audience-count');
                    Route::post('/notifications/send', [NotificationAdminController::class, 'send'])->name('notifications.send');
                    Route::get('/notifications/{id}', [NotificationAdminController::class, 'show'])->whereNumber('id')->name('notifications.show');
                    Route::delete('/notifications/{id}', [NotificationAdminController::class, 'destroy'])->whereNumber('id')->name('notifications.destroy');

                    Route::get('/legal-pages', [LegalPageAdminController::class, 'index'])->name('legal-pages.index');
                    Route::get('/legal-pages/{slug}', [LegalPageAdminController::class, 'show'])
                        ->where('slug', 'privacy_policy|terms_of_use')
                        ->name('legal-pages.show');
                    Route::put('/legal-pages/{slug}', [LegalPageAdminController::class, 'update'])
                        ->where('slug', 'privacy_policy|terms_of_use')
                        ->name('legal-pages.update');

                    Route::get('/kid-listo-quotes', [KidListoQuoteAdminController::class, 'index'])->name('kid-listo-quotes.index');
                    Route::post('/kid-listo-quotes', [KidListoQuoteAdminController::class, 'store'])->name('kid-listo-quotes.store');
                    Route::get('/kid-listo-quotes/{kidListoQuote}', [KidListoQuoteAdminController::class, 'show'])->name('kid-listo-quotes.show');
                    Route::put('/kid-listo-quotes/{kidListoQuote}', [KidListoQuoteAdminController::class, 'update'])->name('kid-listo-quotes.update');
                    Route::delete('/kid-listo-quotes/{kidListoQuote}', [KidListoQuoteAdminController::class, 'destroy'])->name('kid-listo-quotes.destroy');

                    Route::get('/care-toolkit-questions', [CareToolkitQuestionAdminController::class, 'index'])->name('care-toolkit-questions.index');
                    Route::post('/care-toolkit-questions', [CareToolkitQuestionAdminController::class, 'store'])->name('care-toolkit-questions.store');
                    Route::get('/care-toolkit-questions/{careToolkitQuestion}', [CareToolkitQuestionAdminController::class, 'show'])->name('care-toolkit-questions.show');
                    Route::put('/care-toolkit-questions/{careToolkitQuestion}', [CareToolkitQuestionAdminController::class, 'update'])->name('care-toolkit-questions.update');
                    Route::delete('/care-toolkit-questions/{careToolkitQuestion}', [CareToolkitQuestionAdminController::class, 'destroy'])->name('care-toolkit-questions.destroy');

                    Route::get('/care-support-resources', [CareSupportResourceAdminController::class, 'index'])->name('care-support-resources.index');
                    Route::post('/care-support-resources', [CareSupportResourceAdminController::class, 'store'])->name('care-support-resources.store');
                    Route::get('/care-support-resources/{careSupportResource}', [CareSupportResourceAdminController::class, 'show'])->name('care-support-resources.show');
                    Route::put('/care-support-resources/{careSupportResource}', [CareSupportResourceAdminController::class, 'update'])->name('care-support-resources.update');
                    Route::delete('/care-support-resources/{careSupportResource}', [CareSupportResourceAdminController::class, 'destroy'])->name('care-support-resources.destroy');

                    Route::get('/hope-directory', [HopeDirectoryAdminController::class, 'index'])->name('hope-directory.index');
                    Route::post('/hope-directory', [HopeDirectoryAdminController::class, 'store'])->name('hope-directory.store');
                    Route::get('/hope-directory/{hopeDirectory}', [HopeDirectoryAdminController::class, 'show'])->name('hope-directory.show');
                    Route::put('/hope-directory/{hopeDirectory}', [HopeDirectoryAdminController::class, 'update'])->name('hope-directory.update');
                    Route::post('/hope-directory/{hopeDirectory}', [HopeDirectoryAdminController::class, 'update'])->name('hope-directory.update.post');
                    Route::delete('/hope-directory/{hopeDirectory}', [HopeDirectoryAdminController::class, 'destroy'])->name('hope-directory.destroy');

                    Route::get('/hope-events', [HopeEventAdminController::class, 'index'])->name('hope-events.index');
                    Route::post('/hope-events', [HopeEventAdminController::class, 'store'])->name('hope-events.store');
                    Route::get('/hope-events/{hopeEvent}', [HopeEventAdminController::class, 'show'])->name('hope-events.show');
                    Route::put('/hope-events/{hopeEvent}', [HopeEventAdminController::class, 'update'])->name('hope-events.update');
                    Route::post('/hope-events/{hopeEvent}', [HopeEventAdminController::class, 'update'])->name('hope-events.update.post');
                    Route::delete('/hope-events/{hopeEvent}', [HopeEventAdminController::class, 'destroy'])->name('hope-events.destroy');
                });

                // Admin in-app notification inbox (all admin roles)
                Route::middleware('role:super_admin,editor,publisher,analytics_viewer')->group(function (): void {
                    Route::get('/inbox',                 [AdminNotificationController::class, 'index'])->name('admin-inbox.index');
                    Route::get('/inbox/summary',         [AdminNotificationController::class, 'summary'])->name('admin-inbox.summary');
                    Route::post('/inbox/read-all',       [AdminNotificationController::class, 'markAllAsRead'])->name('admin-inbox.read-all');
                    Route::post('/inbox/{notification}/read', [AdminNotificationController::class, 'markAsRead'])->name('admin-inbox.read');
                });

                // Super Admin + Publisher — rehab centers, trainings, contests, IEC materials
                Route::middleware('role:super_admin,publisher')
                    ->group(function (): void {
                        Route::get('/rehab-centers',              [RehabCenterAdminController::class, 'index'])->name('rehab-centers.index');
                        Route::post('/rehab-centers',             [RehabCenterAdminController::class, 'store'])->name('rehab-centers.store');
                        Route::get('/rehab-centers/{rehabCenter}', [RehabCenterAdminController::class, 'show'])->name('rehab-centers.show');
                        Route::put('/rehab-centers/{rehabCenter}', [RehabCenterAdminController::class, 'update'])->name('rehab-centers.update');
                        Route::delete('/rehab-centers/{rehabCenter}', [RehabCenterAdminController::class, 'destroy'])->name('rehab-centers.destroy');

                        Route::get('/trainings',              [TrainingAdminController::class, 'index'])->name('trainings.index');
                        Route::post('/trainings',             [TrainingAdminController::class, 'store'])->name('trainings.store');
                        Route::get('/trainings/{training}',   [TrainingAdminController::class, 'show'])->name('trainings.show');
                        Route::put('/trainings/{training}',   [TrainingAdminController::class, 'update'])->name('trainings.update');
                        Route::delete('/trainings/{training}', [TrainingAdminController::class, 'destroy'])->name('trainings.destroy');

                        Route::get('/contests', [ContestAdminController::class, 'index'])->name('contests.index');
                        Route::post('/contests', [ContestAdminController::class, 'store'])->name('contests.store');
                        Route::get('/contests/{contest}', [ContestAdminController::class, 'show'])->name('contests.show');
                        Route::put('/contests/{contest}', [ContestAdminController::class, 'update'])->name('contests.update');
                        Route::delete('/contests/{contest}', [ContestAdminController::class, 'destroy'])->name('contests.destroy');
                        Route::get('/contests/{contest}/entries', [ContestAdminController::class, 'entries'])->name('contests.entries');
                        Route::post('/contests/entries/{contestEntry}/review', [ContestAdminController::class, 'review'])->name('contests.entries.review');
                        Route::post('/contests/entries/{contestEntry}/winner', [ContestAdminController::class, 'setWinner'])->name('contests.entries.winner');

                        Route::get('/iec-materials', [IecMaterialAdminController::class, 'index'])->name('iec-materials.index');
                        Route::post('/iec-materials', [IecMaterialAdminController::class, 'store'])->name('iec-materials.store');
                        Route::get('/iec-materials/{iecMaterial}', [IecMaterialAdminController::class, 'show'])->name('iec-materials.show');
                        Route::put('/iec-materials/{iecMaterial}', [IecMaterialAdminController::class, 'update'])->name('iec-materials.update');
                        Route::delete('/iec-materials/{iecMaterial}', [IecMaterialAdminController::class, 'destroy'])->name('iec-materials.destroy');

                        Route::get('/app-translations', [AppTranslationAdminController::class, 'index'])->name('app-translations.index');
                        Route::post('/app-translations', [AppTranslationAdminController::class, 'store'])->name('app-translations.store');
                        Route::get('/app-translations/{appTranslation}', [AppTranslationAdminController::class, 'show'])->name('app-translations.show');
                        Route::put('/app-translations/{appTranslation}', [AppTranslationAdminController::class, 'update'])->name('app-translations.update');
                        Route::delete('/app-translations/{appTranslation}', [AppTranslationAdminController::class, 'destroy'])->name('app-translations.destroy');
                    });

                // Analytics Viewer + Super Admin
                Route::middleware('role:analytics_viewer,super_admin')->group(function (): void {
                    Route::get('/analytics',        [AnalyticsAdminController::class, 'index'])->name('analytics.index');
                    Route::get('/analytics/export', [AnalyticsAdminController::class, 'export'])->name('analytics.export');
                });
            });
        });
    });
