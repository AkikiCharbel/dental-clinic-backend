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
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Personal information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('preferred_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->default('prefer_not_to_say');

            // Contact information
            $table->string('phone', 50)->nullable();
            $table->string('phone_secondary', 50)->nullable();
            $table->string('email')->nullable();

            // Contact preferences
            $table->string('preferred_contact_method', 20)->default('phone');
            $table->boolean('contact_consent')->default(true);
            $table->boolean('marketing_consent')->default(false);

            // Address (stored as JSON for flexibility)
            $table->json('address')->nullable();

            // Provider preferences
            $table->foreignId('preferred_location_id')->nullable();
            $table->foreignId('preferred_dentist_id')->nullable();

            // Status and financial
            $table->string('status', 20)->default('active');
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->string('outstanding_balance_currency', 3)->default('USD');

            // Medical info (encrypted at application level)
            $table->text('medical_notes')->nullable();
            $table->json('allergies')->nullable();
            $table->json('medications')->nullable();

            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('emergency_contact_relationship')->nullable();

            // Insurance (basic - can be expanded)
            $table->string('insurance_provider')->nullable();
            $table->string('insurance_policy_number')->nullable();
            $table->string('insurance_group_number')->nullable();

            // Metadata
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'first_name', 'last_name']);
            $table->index(['tenant_id', 'date_of_birth']);
            $table->index(['tenant_id', 'preferred_dentist_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        // Create full-text search index using PostgreSQL tsvector (only for PostgreSQL)
        // This enables fast patient search across multiple fields
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Illuminate\Support\Facades\DB::statement("
                ALTER TABLE patients ADD COLUMN search_vector tsvector
                GENERATED ALWAYS AS (
                    setweight(to_tsvector('english', coalesce(first_name, '')), 'A') ||
                    setweight(to_tsvector('english', coalesce(last_name, '')), 'A') ||
                    setweight(to_tsvector('english', coalesce(preferred_name, '')), 'B') ||
                    setweight(to_tsvector('english', coalesce(email, '')), 'C') ||
                    setweight(to_tsvector('english', coalesce(phone, '')), 'C')
                ) STORED
            ");

            Illuminate\Support\Facades\DB::statement('CREATE INDEX patients_search_idx ON patients USING GIN (search_vector)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
