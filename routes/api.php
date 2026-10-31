<?php

use App\Http\Controllers\API\Admin\AdminNotificationController;
use App\Http\Controllers\API\Admin\AdminSearch\AdminSearchController;
use App\Http\Controllers\API\Admin\AnalyticsAdminController;
use App\Http\Controllers\API\Admin\DashboardAdminController;
use App\Http\Controllers\API\Admin\CategoryAdminController;
use App\Http\Controllers\API\Admin\DiaryAdminController;
use App\Http\Controllers\API\Admin\NotificationAdminController;
use App\Http\Controllers\API\Admin\PostAdminController;
use App\Http\Controllers\API\Admin\PostComment\PostCommentAdminController;
use App\Http\Controllers\API\Admin\RehabCenterAdminController;
use App\Http\Controllers\API\Admin\TrainingAdminController;
use App\Http\Controllers\API\Admin\ContestAdminController;
use App\Http\Controllers\API\Admin\IecMaterialAdminController;
use App\Http\Controllers\API\Admin\AppTranslationAdminController;
use App\Http\Controllers\API\Admin\UserAdminController;
use App\Http\Controllers\API\Admin\AuditLogAdminController;
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
use App\Http\Controllers\API\AppOptionController;
use App\Http\Controllers\API\Admin\AppOptionAdminController;
use App\Http\Controllers\API\BlockedWordController;
use App\Http\Controllers\API\KidListoQuoteController;
use App\Http\Controllers\API\MoodCheckinController;
use App\Http\Controllers\API\Admin\LegalPageAdminController;
use App\Http\Controllers\API\Admin\BlockedWordAdminController;
use App\Http\Controllers\API\Admin\KidListoQuoteAdminController;
use App\Http\Controllers\API\CareToolkitQuestionController;
use App\Http\Controllers\API\Admin\CareToolkitQuestionAdminController;
use App\Http\Controllers\API\CareCheck\CareCheckController;
use App\Http\Controllers\API\Admin\CareCheck\CareCheckAdminController;
use App\Http\Controllers\API\CareSupportResourceController;
use App\Http\Controllers\API\Admin\CareSupportResourceAdminController;
use App\Http\Controllers\API\HopeDirectoryController;
use App\Http\Controllers\API\HopeEventController;
use App\Http\Controllers\API\Admin\HopeDirectoryAdminController;
use App\Http\Controllers\API\Admin\HopeEventAdminController;
use App\Http\Controllers\API\Admin\IconPicker\IconPickerController;
use App\Http\Controllers\API\Geocode\GeocodeController;
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
            Route::get('/check-email', [AuthController::class, 'checkEmail'])
                ->middleware('throttle:20,1')
                ->name('check-email');
            Route::post('/register', [AuthController::class, 'register'])->name('register');
            Route::post('/login', [AuthController::class, 'login'])->name('login');
            Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
            Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode'])->name('verify-reset-code');
            Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
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
                ->where('id', '[0-9]+|[0-9a-fA-F-]{36}')
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
            ->where('id', '[0-9]+|[0-9a-fA-F-]{36}')
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
            ->where('contest', '[0-9]+|[0-9a-fA-F-]{36}')
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

        // ── Public legal pages (Privacy Policy / Terms of Use / Privacy Notice) ─
        Route::get('/legal-pages', [LegalPageController::class, 'index'])
            ->name('legal-pages.index');
        Route::get('/legal-pages/{slug}', [LegalPageController::class, 'show'])
            ->where('slug', 'privacy_policy|terms_of_use|privacy_notice')
            ->name('legal-pages.show');

        Route::get('/app-options', [AppOptionController::class, 'index'])
            ->name('app-options.index');

        Route::get('/blocked-words', [BlockedWordController::class, 'index'])
            ->name('blocked-words.index');

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
        Route::middleware(['auth:sanctum', 'user.active', 'password.current'])->group(function (): void {

            Route::get('/geocode/search', [GeocodeController::class, 'search'])
                ->middleware('throttle:30,1')
                ->name('geocode.search');
            Route::get('/geocode/reverse', [GeocodeController::class, 'reverse'])
                ->middleware('throttle:30,1')
                ->name('geocode.reverse');

            Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::post('/auth/login-code', [AuthController::class, 'sendLoginCode'])->name('auth.login-code');
            Route::post('/auth/verify-login-code', [AuthController::class, 'verifyLoginCode'])->name('auth.verify-login-code');
            Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
            Route::get('/me/profile', [ProfileStatsController::class, 'show'])->name('me.profile');
            Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
            Route::post('/auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update.post');
            Route::put('/auth/onboarding', [AuthController::class, 'updateOnboarding'])->name('auth.onboarding.update');
            Route::post('/auth/onboarding', [AuthController::class, 'updateOnboarding'])->name('auth.onboarding.update.post');
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

            Route::get('/care-checks/latest', [CareCheckController::class, 'latest'])->name('care-checks.latest');
            Route::get('/care-checks', [CareCheckController::class, 'index'])->name('care-checks.index');
            Route::post('/care-checks', [CareCheckController::class, 'store'])->name('care-checks.store');

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
            Route::match(['put', 'post'], '/posts/{id}/comments/{commentId}', [PostEngagementController::class, 'updateComment'])
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
            Route::prefix('admin')->name('admin.')->middleware('audit.admin')->group(function (): void {

                // Super Admin only
                Route::middleware('role:super_admin')->group(function (): void {
                    Route::get('/audit-logs', [AuditLogAdminController::class, 'index'])->name('audit-logs.index');
                    Route::get('/audit-logs/summary', [AuditLogAdminController::class, 'summary'])->name('audit-logs.summary');
                    Route::get('/audit-logs/export', [AuditLogAdminController::class, 'export'])->name('audit-logs.export');
                    Route::get('/audit-logs/{auditLog}', [AuditLogAdminController::class, 'show'])->name('audit-logs.show');

                    Route::get('/users',                     [UserAdminController::class, 'index'])->name('users.index');
                    Route::post('/users',                    [UserAdminController::class, 'store'])->name('users.store');
                    Route::put('/users/{user}',              [UserAdminController::class, 'update'])->name('users.update');
                    Route::put('/users/{user}/role',         [UserAdminController::class, 'updateRole'])->name('users.role');
                    Route::post('/users/{user}/force-password', [UserAdminController::class, 'forcePassword'])->name('users.force-password');
                    Route::post('/users/{user}/suspend',     [UserAdminController::class, 'suspend'])->name('users.suspend');
                    Route::post('/users/{user}/activate',    [UserAdminController::class, 'activate'])->name('users.activate');
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
                    Route::delete('/posts/{post}/comments/{comment}', [PostCommentAdminController::class, 'destroy'])
                        ->whereNumber('comment')
                        ->name('posts.comments.destroy');
                });

                // Editor + Super Admin — draft creation, editing, and submission
                Route::middleware('role:editor,super_admin')->group(function (): void {
                    Route::post('/posts',             [PostAdminController::class, 'store'])->name('posts.store');
                    Route::put('/posts/{post}',       [PostAdminController::class, 'update'])->name('posts.update');
                    Route::put('/posts/{post}/submit', [PostAdminController::class, 'submit'])->name('posts.submit');
                });

                // Publisher + Super Admin — review and scheduling
                Route::middleware('role:publisher,super_admin')->group(function (): void {
                    Route::put('/posts/{post}/reject',   [PostAdminController::class, 'reject'])->name('posts.reject');
                    Route::put('/posts/{post}/schedule', [PostAdminController::class, 'schedule'])->name('posts.schedule');
                    Route::put('/posts/{post}/publish',  [PostAdminController::class, 'publish'])->name('posts.publish');
                });

                // Workflow modules — read (Editor, Publisher, Super Admin)
                Route::middleware('role:editor,publisher,super_admin')->group(function (): void {
                    Route::get('/notifications', [NotificationAdminController::class, 'index'])->name('notifications.index');
                    Route::get('/notifications/audience-count', [NotificationAdminController::class, 'audienceCount'])->name('notifications.audience-count');
                    Route::get('/notifications/{notification}', [NotificationAdminController::class, 'show'])->name('notifications.show');

                    Route::get('/legal-pages', [LegalPageAdminController::class, 'index'])->name('legal-pages.index');
                    Route::get('/legal-pages/{slug}', [LegalPageAdminController::class, 'show'])
                        ->where('slug', 'privacy_policy|terms_of_use|privacy_notice')
                        ->name('legal-pages.show');

                    Route::get('/blocked-words', [BlockedWordAdminController::class, 'index'])->name('blocked-words.index');
                    Route::get('/blocked-words/{blockedWord}', [BlockedWordAdminController::class, 'show'])->name('blocked-words.show');

                    Route::get('/kid-listo-quotes', [KidListoQuoteAdminController::class, 'index'])->name('kid-listo-quotes.index');
                    Route::get('/kid-listo-quotes/{kidListoQuote}', [KidListoQuoteAdminController::class, 'show'])->name('kid-listo-quotes.show');

                    Route::get('/icon-picker/iconsets', [IconPickerController::class, 'iconsets'])->name('icon-picker.iconsets');
                    Route::get('/icon-picker/iconsets/{iconset}', [IconPickerController::class, 'icons'])->name('icon-picker.icons');

                    Route::get('/app-options', [AppOptionAdminController::class, 'index'])->name('app-options.index');
                    Route::get('/app-options/{appOption}', [AppOptionAdminController::class, 'show'])->name('app-options.show');

                    Route::get('/care-checks/summary', [CareCheckAdminController::class, 'summary'])->name('care-checks.summary');
                    Route::get('/care-checks', [CareCheckAdminController::class, 'index'])->name('care-checks.index');
                    Route::get('/care-checks/{id}', [CareCheckAdminController::class, 'show'])->whereNumber('id')->name('care-checks.show');

                    Route::get('/care-toolkit-questions', [CareToolkitQuestionAdminController::class, 'index'])->name('care-toolkit-questions.index');
                    Route::get('/care-toolkit-questions/{careToolkitQuestion}', [CareToolkitQuestionAdminController::class, 'show'])->name('care-toolkit-questions.show');

                    Route::get('/care-support-resources', [CareSupportResourceAdminController::class, 'index'])->name('care-support-resources.index');
                    Route::get('/care-support-resources/{careSupportResource}', [CareSupportResourceAdminController::class, 'show'])->name('care-support-resources.show');

                    Route::get('/hope-directory', [HopeDirectoryAdminController::class, 'index'])->name('hope-directory.index');
                    Route::get('/hope-directory/{hopeDirectory}', [HopeDirectoryAdminController::class, 'show'])->name('hope-directory.show');

                    Route::get('/hope-events', [HopeEventAdminController::class, 'index'])->name('hope-events.index');
                    Route::get('/hope-events/options', [HopeEventAdminController::class, 'options'])->name('hope-events.options');
                    Route::get('/hope-events/{hopeEvent}', [HopeEventAdminController::class, 'show'])->name('hope-events.show');

                    Route::get('/contests', [ContestAdminController::class, 'index'])->name('contests.index');
                    Route::get('/contests/{contest}', [ContestAdminController::class, 'show'])->name('contests.show');
                    Route::get('/contests/{contest}/entries', [ContestAdminController::class, 'entries'])->name('contests.entries');

                    Route::get('/iec-materials', [IecMaterialAdminController::class, 'index'])->name('iec-materials.index');
                    Route::get('/iec-materials/{iecMaterial}', [IecMaterialAdminController::class, 'show'])->name('iec-materials.show');

                    Route::get('/app-translations', [AppTranslationAdminController::class, 'index'])->name('app-translations.index');
                    Route::get('/app-translations/{appTranslation}', [AppTranslationAdminController::class, 'show'])->name('app-translations.show');
                });

                // Workflow modules — draft, edit, submit (Editor, Super Admin)
                Route::middleware('role:editor,super_admin')->group(function (): void {
                    Route::post('/notifications/send', [NotificationAdminController::class, 'send'])->name('notifications.send');
                    Route::put('/notifications/{notification}/submit', [NotificationAdminController::class, 'submit'])->name('notifications.submit');

                    Route::put('/legal-pages/{slug}', [LegalPageAdminController::class, 'update'])
                        ->where('slug', 'privacy_policy|terms_of_use|privacy_notice')
                        ->name('legal-pages.update');
                    Route::put('/legal-pages/{slug}/submit', [LegalPageAdminController::class, 'submit'])
                        ->where('slug', 'privacy_policy|terms_of_use|privacy_notice')
                        ->name('legal-pages.submit');

                    Route::post('/blocked-words', [BlockedWordAdminController::class, 'store'])->name('blocked-words.store');
                    Route::put('/blocked-words/{blockedWord}', [BlockedWordAdminController::class, 'update'])->name('blocked-words.update');
                    Route::put('/blocked-words/{blockedWord}/submit', [BlockedWordAdminController::class, 'submit'])->name('blocked-words.submit');

                    Route::post('/kid-listo-quotes', [KidListoQuoteAdminController::class, 'store'])->name('kid-listo-quotes.store');
                    Route::put('/kid-listo-quotes/{kidListoQuote}', [KidListoQuoteAdminController::class, 'update'])->name('kid-listo-quotes.update');
                    Route::put('/kid-listo-quotes/{kidListoQuote}/submit', [KidListoQuoteAdminController::class, 'submit'])->name('kid-listo-quotes.submit');

                    Route::post('/app-options', [AppOptionAdminController::class, 'store'])->name('app-options.store');
                    Route::put('/app-options/{appOption}', [AppOptionAdminController::class, 'update'])->name('app-options.update');
                    Route::put('/app-options/{appOption}/submit', [AppOptionAdminController::class, 'submit'])->name('app-options.submit');

                    Route::post('/care-toolkit-questions', [CareToolkitQuestionAdminController::class, 'store'])->name('care-toolkit-questions.store');
                    Route::put('/care-toolkit-questions/{careToolkitQuestion}', [CareToolkitQuestionAdminController::class, 'update'])->name('care-toolkit-questions.update');
                    Route::put('/care-toolkit-questions/{careToolkitQuestion}/submit', [CareToolkitQuestionAdminController::class, 'submit'])->name('care-toolkit-questions.submit');

                    Route::post('/care-support-resources', [CareSupportResourceAdminController::class, 'store'])->name('care-support-resources.store');
                    Route::put('/care-support-resources/{careSupportResource}', [CareSupportResourceAdminController::class, 'update'])->name('care-support-resources.update');
                    Route::put('/care-support-resources/{careSupportResource}/submit', [CareSupportResourceAdminController::class, 'submit'])->name('care-support-resources.submit');

                    Route::post('/hope-directory', [HopeDirectoryAdminController::class, 'store'])->name('hope-directory.store');
                    Route::put('/hope-directory/{hopeDirectory}', [HopeDirectoryAdminController::class, 'update'])->name('hope-directory.update');
                    Route::put('/hope-directory/{hopeDirectory}/submit', [HopeDirectoryAdminController::class, 'submit'])->name('hope-directory.submit');
                    Route::post('/hope-directory/{hopeDirectory}', [HopeDirectoryAdminController::class, 'update'])->name('hope-directory.update.post');

                    Route::post('/hope-events', [HopeEventAdminController::class, 'store'])->name('hope-events.store');
                    Route::put('/hope-events/{hopeEvent}', [HopeEventAdminController::class, 'update'])->name('hope-events.update');
                    Route::put('/hope-events/{hopeEvent}/submit', [HopeEventAdminController::class, 'submit'])->name('hope-events.submit');
                    Route::post('/hope-events/{hopeEvent}', [HopeEventAdminController::class, 'update'])->name('hope-events.update.post');

                    Route::post('/contests', [ContestAdminController::class, 'store'])->name('contests.store');
                    Route::put('/contests/{contest}', [ContestAdminController::class, 'update'])->name('contests.update');
                    Route::put('/contests/{contest}/submit', [ContestAdminController::class, 'submit'])->name('contests.submit');

                    Route::post('/iec-materials', [IecMaterialAdminController::class, 'store'])->name('iec-materials.store');
                    Route::put('/iec-materials/{iecMaterial}', [IecMaterialAdminController::class, 'update'])->name('iec-materials.update');
                    Route::put('/iec-materials/{iecMaterial}/submit', [IecMaterialAdminController::class, 'submit'])->name('iec-materials.submit');

                    Route::post('/app-translations', [AppTranslationAdminController::class, 'store'])->name('app-translations.store');
                    Route::put('/app-translations/{appTranslation}', [AppTranslationAdminController::class, 'update'])->name('app-translations.update');
                    Route::put('/app-translations/{appTranslation}/submit', [AppTranslationAdminController::class, 'submit'])->name('app-translations.submit');
                    Route::post('/app-translations/{appTranslation}', [AppTranslationAdminController::class, 'update'])->name('app-translations.update.post');
                });

                // Workflow modules — review and publish (Publisher, Super Admin)
                Route::middleware('role:publisher,super_admin')->group(function (): void {
                    Route::put('/notifications/{notification}/reject', [NotificationAdminController::class, 'reject'])->name('notifications.reject');
                    Route::put('/notifications/{notification}/publish', [NotificationAdminController::class, 'publish'])->name('notifications.publish');

                    Route::put('/legal-pages/{slug}/reject', [LegalPageAdminController::class, 'reject'])
                        ->where('slug', 'privacy_policy|terms_of_use|privacy_notice')
                        ->name('legal-pages.reject');
                    Route::put('/legal-pages/{slug}/publish', [LegalPageAdminController::class, 'publish'])
                        ->where('slug', 'privacy_policy|terms_of_use|privacy_notice')
                        ->name('legal-pages.publish');

                    Route::put('/blocked-words/{blockedWord}/reject', [BlockedWordAdminController::class, 'reject'])->name('blocked-words.reject');
                    Route::put('/blocked-words/{blockedWord}/publish', [BlockedWordAdminController::class, 'publish'])->name('blocked-words.publish');

                    Route::put('/kid-listo-quotes/{kidListoQuote}/reject', [KidListoQuoteAdminController::class, 'reject'])->name('kid-listo-quotes.reject');
                    Route::put('/kid-listo-quotes/{kidListoQuote}/publish', [KidListoQuoteAdminController::class, 'publish'])->name('kid-listo-quotes.publish');

                    Route::put('/app-options/{appOption}/reject', [AppOptionAdminController::class, 'reject'])->name('app-options.reject');
                    Route::put('/app-options/{appOption}/publish', [AppOptionAdminController::class, 'publish'])->name('app-options.publish');

                    Route::put('/care-toolkit-questions/{careToolkitQuestion}/reject', [CareToolkitQuestionAdminController::class, 'reject'])->name('care-toolkit-questions.reject');
                    Route::put('/care-toolkit-questions/{careToolkitQuestion}/publish', [CareToolkitQuestionAdminController::class, 'publish'])->name('care-toolkit-questions.publish');

                    Route::put('/care-support-resources/{careSupportResource}/reject', [CareSupportResourceAdminController::class, 'reject'])->name('care-support-resources.reject');
                    Route::put('/care-support-resources/{careSupportResource}/publish', [CareSupportResourceAdminController::class, 'publish'])->name('care-support-resources.publish');

                    Route::put('/hope-directory/{hopeDirectory}/reject', [HopeDirectoryAdminController::class, 'reject'])->name('hope-directory.reject');
                    Route::put('/hope-directory/{hopeDirectory}/publish', [HopeDirectoryAdminController::class, 'publish'])->name('hope-directory.publish');

                    Route::put('/hope-events/{hopeEvent}/reject', [HopeEventAdminController::class, 'reject'])->name('hope-events.reject');
                    Route::put('/hope-events/{hopeEvent}/publish', [HopeEventAdminController::class, 'publish'])->name('hope-events.publish');

                    Route::put('/contests/{contest}/reject', [ContestAdminController::class, 'reject'])->name('contests.reject');
                    Route::put('/contests/{contest}/publish', [ContestAdminController::class, 'publish'])->name('contests.publish');
                    Route::post('/contests/entries/{contestEntry}/review', [ContestAdminController::class, 'review'])->name('contests.entries.review');
                    Route::post('/contests/entries/{contestEntry}/winner', [ContestAdminController::class, 'setWinner'])->name('contests.entries.winner');

                    Route::put('/iec-materials/{iecMaterial}/reject', [IecMaterialAdminController::class, 'reject'])->name('iec-materials.reject');
                    Route::put('/iec-materials/{iecMaterial}/publish', [IecMaterialAdminController::class, 'publish'])->name('iec-materials.publish');

                    Route::put('/app-translations/{appTranslation}/reject', [AppTranslationAdminController::class, 'reject'])->name('app-translations.reject');
                    Route::put('/app-translations/{appTranslation}/publish', [AppTranslationAdminController::class, 'publish'])->name('app-translations.publish');
                });

                // Workflow modules — destroy (Super Admin)
                Route::middleware('role:super_admin')->group(function (): void {
                    Route::delete('/notifications/{notification}', [NotificationAdminController::class, 'destroy'])->name('notifications.destroy');

                    Route::delete('/blocked-words/{blockedWord}', [BlockedWordAdminController::class, 'destroy'])->name('blocked-words.destroy');
                    Route::delete('/kid-listo-quotes/{kidListoQuote}', [KidListoQuoteAdminController::class, 'destroy'])->name('kid-listo-quotes.destroy');
                    Route::delete('/app-options/{appOption}', [AppOptionAdminController::class, 'destroy'])->name('app-options.destroy');
                    Route::delete('/care-toolkit-questions/{careToolkitQuestion}', [CareToolkitQuestionAdminController::class, 'destroy'])->name('care-toolkit-questions.destroy');
                    Route::delete('/care-support-resources/{careSupportResource}', [CareSupportResourceAdminController::class, 'destroy'])->name('care-support-resources.destroy');
                    Route::delete('/hope-directory/{hopeDirectory}', [HopeDirectoryAdminController::class, 'destroy'])->name('hope-directory.destroy');
                    Route::delete('/hope-events/{hopeEvent}', [HopeEventAdminController::class, 'destroy'])->name('hope-events.destroy');
                    Route::delete('/contests/{contest}', [ContestAdminController::class, 'destroy'])->name('contests.destroy');
                    Route::delete('/iec-materials/{iecMaterial}', [IecMaterialAdminController::class, 'destroy'])->name('iec-materials.destroy');
                    Route::delete('/app-translations/{appTranslation}', [AppTranslationAdminController::class, 'destroy'])->name('app-translations.destroy');
                });

                // Admin in-app notification inbox (all admin roles)
                Route::middleware('role:super_admin,editor,publisher,analytics_viewer')->group(function (): void {
                    Route::get('/search',                [AdminSearchController::class, 'index'])->name('admin-search.index');
                    Route::get('/inbox',                 [AdminNotificationController::class, 'index'])->name('admin-inbox.index');
                    Route::get('/inbox/summary',         [AdminNotificationController::class, 'summary'])->name('admin-inbox.summary');
                    Route::post('/inbox/read-all',       [AdminNotificationController::class, 'markAllAsRead'])->name('admin-inbox.read-all');
                    Route::post('/inbox/{notification}/read', [AdminNotificationController::class, 'markAsRead'])->name('admin-inbox.read');
                    Route::get('/dashboard/attention', [DashboardAdminController::class, 'attention'])->name('dashboard.attention');
                    Route::get('/dashboard/trends', [DashboardAdminController::class, 'trends'])->name('dashboard.trends');
                    Route::get('/dashboard/overview', [DashboardAdminController::class, 'overview'])->name('dashboard.overview');
                });

                // Rehab centers and trainings — shared directory CRUD + archive (Editor, Publisher, Super Admin)
                Route::middleware('role:editor,publisher,super_admin')->group(function (): void {
                    Route::get('/rehab-centers', [RehabCenterAdminController::class, 'index'])->name('rehab-centers.index');
                    Route::post('/rehab-centers', [RehabCenterAdminController::class, 'store'])->name('rehab-centers.store');
                    Route::get('/rehab-centers/{rehabCenter}', [RehabCenterAdminController::class, 'show'])->name('rehab-centers.show');
                    Route::put('/rehab-centers/{rehabCenter}', [RehabCenterAdminController::class, 'update'])->name('rehab-centers.update');
                    Route::put('/rehab-centers/{rehabCenter}/archive', [RehabCenterAdminController::class, 'archive'])->name('rehab-centers.archive');
                    Route::put('/rehab-centers/{rehabCenter}/restore', [RehabCenterAdminController::class, 'restore'])->name('rehab-centers.restore');

                    Route::get('/trainings', [TrainingAdminController::class, 'index'])->name('trainings.index');
                    Route::post('/trainings', [TrainingAdminController::class, 'store'])->name('trainings.store');
                    Route::get('/trainings/{training}', [TrainingAdminController::class, 'show'])->name('trainings.show');
                    Route::put('/trainings/{training}', [TrainingAdminController::class, 'update'])->name('trainings.update');
                    Route::put('/trainings/{training}/archive', [TrainingAdminController::class, 'archive'])->name('trainings.archive');
                    Route::put('/trainings/{training}/restore', [TrainingAdminController::class, 'restore'])->name('trainings.restore');
                });

                Route::middleware('role:super_admin')->group(function (): void {
                    Route::delete('/rehab-centers/{rehabCenter}', [RehabCenterAdminController::class, 'destroy'])->name('rehab-centers.destroy');
                    Route::delete('/trainings/{training}', [TrainingAdminController::class, 'destroy'])->name('trainings.destroy');
                });

                // Analytics Viewer + Super Admin
                Route::middleware('role:analytics_viewer,super_admin')->group(function (): void {
                    Route::get('/analytics',        [AnalyticsAdminController::class, 'index'])->name('analytics.index');
                    Route::get('/analytics/export', [AnalyticsAdminController::class, 'export'])->name('analytics.export');
                });
            });
        });
    });
