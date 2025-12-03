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
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('country_code', 3)->nullable();

            // Subscription management
            $table->string('subscription_status', 20)->default('trial');
            $table->string('subscription_plan', 20)->default('basic');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            // Localization settings
            $table->string('default_currency', 3)->default('USD');
            $table->string('timezone', 50)->default('UTC');
            $table->string('locale', 10)->default('en');

            // Feature flags and settings
            $table->jsonb('features')->nullable();
            $table->jsonb('settings')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('is_active');
            $table->index('country_code');
            $table->index(['subscription_status', 'is_active']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
