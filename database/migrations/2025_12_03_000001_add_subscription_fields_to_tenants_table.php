<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            // Subscription fields
            $table->string('subscription_status')->default('trial')->after('address');
            $table->string('subscription_plan')->default('basic')->after('subscription_status');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_plan');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');

            // Localization fields
            $table->string('default_currency', 3)->default('USD')->after('subscription_ends_at');
            $table->string('timezone')->default('UTC')->after('default_currency');
            $table->string('locale', 10)->default('en')->after('timezone');
            $table->string('country_code', 2)->nullable()->after('locale');

            // Features (stored separately from settings for clearer separation)
            $table->json('features')->nullable()->after('settings');

            // Indexes for common queries
            $table->index(['subscription_status', 'is_active']);
            $table->index('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['subscription_status', 'is_active']);
            $table->dropIndex(['country_code']);

            $table->dropColumn([
                'subscription_status',
                'subscription_plan',
                'trial_ends_at',
                'subscription_ends_at',
                'default_currency',
                'timezone',
                'locale',
                'country_code',
                'features',
            ]);
        });
    }
};
