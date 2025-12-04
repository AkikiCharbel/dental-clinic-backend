<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            // Personal information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('preferred_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();

            // Contact information
            $table->string('phone', 50)->nullable();
            $table->string('phone_secondary', 50)->nullable();
            $table->string('email')->nullable();

            // Communication preferences
            $table->string('preferred_contact_method', 20)->default('phone');
            $table->boolean('contact_consent')->default(true);
            $table->boolean('marketing_consent')->default(false);

            // Address (stored as JSONB for flexibility)
            $table->jsonb('address')->nullable();

            // Care preferences
            $table->foreignUuid('preferred_location_id')->nullable();
            $table->foreignUuid('preferred_dentist_id')->nullable();

            // Status and financial
            $table->string('status', 20)->default('active');
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->string('outstanding_balance_currency', 3)->default('USD');

            // Metadata
            $table->jsonb('medical_alerts')->nullable();
            $table->jsonb('insurance_info')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'first_name', 'last_name']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'date_of_birth']);
            $table->index('created_at');
        });

        // Add full-text search vector (PostgreSQL specific)
        if (config('database.default') === 'pgsql') {
            // Add tsvector column using raw SQL (not supported by Laravel Blueprint)
            DB::statement('ALTER TABLE patients ADD COLUMN search_vector tsvector');

            // Create GIN index for full-text search
            DB::statement('CREATE INDEX patients_search_vector_gin ON patients USING GIN(search_vector)');

            // Create trigger to update search vector
            DB::statement("
                CREATE OR REPLACE FUNCTION patients_search_vector_update() RETURNS trigger AS $$
                BEGIN
                    NEW.search_vector := to_tsvector('english',
                        coalesce(NEW.first_name, '') || ' ' ||
                        coalesce(NEW.last_name, '') || ' ' ||
                        coalesce(NEW.middle_name, '') || ' ' ||
                        coalesce(NEW.preferred_name, '') || ' ' ||
                        coalesce(NEW.email, '') || ' ' ||
                        coalesce(NEW.phone, '')
                    );
                    RETURN NEW;
                END
                $$ LANGUAGE plpgsql;
            ");

            DB::statement('
                CREATE TRIGGER patients_search_vector_trigger
                BEFORE INSERT OR UPDATE ON patients
                FOR EACH ROW EXECUTE FUNCTION patients_search_vector_update();
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS patients_search_vector_trigger ON patients');
            DB::statement('DROP FUNCTION IF EXISTS patients_search_vector_update()');
        }

        Schema::dropIfExists('patients');
    }
};
