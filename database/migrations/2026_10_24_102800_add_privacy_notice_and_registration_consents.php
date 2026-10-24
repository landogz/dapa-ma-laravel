<?php

use App\Models\LegalPage;
use App\Support\PrivacyNoticeContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('accepted_terms_of_use_at')->nullable()->after('onboarding_completed_at');
            $table->timestamp('accepted_privacy_policy_at')->nullable()->after('accepted_terms_of_use_at');
            $table->timestamp('accepted_privacy_notice_at')->nullable()->after('accepted_privacy_policy_at');
        });

        LegalPage::query()->updateOrCreate(
            ['slug' => 'privacy_notice'],
            PrivacyNoticeContent::page(),
        );
    }

    public function down(): void
    {
        LegalPage::query()->where('slug', 'privacy_notice')->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'accepted_terms_of_use_at',
                'accepted_privacy_policy_at',
                'accepted_privacy_notice_at',
            ]);
        });
    }
};
