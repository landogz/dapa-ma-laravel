<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 120)->nullable()->after('last_name');
            $table->string('pronouns', 64)->nullable()->after('nickname');
            $table->date('birthday')->nullable()->after('pronouns');
            $table->string('persona', 64)->nullable()->after('birthday');
            $table->json('interests')->nullable()->after('persona');
            $table->timestamp('onboarding_completed_at')->nullable()->after('interests');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nickname',
                'pronouns',
                'birthday',
                'persona',
                'interests',
                'onboarding_completed_at',
            ]);
        });
    }
};
