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
        Schema::table('users', function (Blueprint $table): void {
            // Profile fields - restructure name into first/last
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('title', 20)->nullable()->after('last_name');

            // Role field (primary role for the user)
            $table->string('primary_role')->default('receptionist')->after('title');

            // Contact and professional info
            $table->string('phone', 50)->nullable()->after('email');
            $table->string('license_number', 100)->nullable()->after('phone');
            $table->string('specialization')->nullable()->after('license_number');

            // Tracking fields
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            // Preferences
            $table->jsonb('preferences')->nullable()->after('last_login_ip');

            // Additional indexes
            $table->index(['tenant_id', 'primary_role']);
            $table->index('last_login_at');
        });

        // Migrate existing 'name' data to first_name/last_name
        // This is done safely by splitting on the first space
        Illuminate\Support\Facades\DB::statement("
            UPDATE users
            SET
                first_name = CASE
                    WHEN position(' ' in name) > 0 THEN substring(name from 1 for position(' ' in name) - 1)
                    ELSE name
                END,
                last_name = CASE
                    WHEN position(' ' in name) > 0 THEN substring(name from position(' ' in name) + 1)
                    ELSE NULL
                END
            WHERE first_name IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'primary_role']);
            $table->dropIndex(['last_login_at']);

            $table->dropColumn([
                'first_name',
                'last_name',
                'title',
                'primary_role',
                'phone',
                'license_number',
                'specialization',
                'last_login_at',
                'last_login_ip',
                'preferences',
            ]);
        });
    }
};
