<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactMethod;
use App\Enums\Gender;
use App\Enums\PatientStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string|null $preferred_name
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property Gender $gender
 * @property string|null $phone
 * @property string|null $phone_secondary
 * @property string|null $email
 * @property ContactMethod $preferred_contact_method
 * @property bool $contact_consent
 * @property bool $marketing_consent
 * @property array<string, mixed>|null $address
 * @property int|null $preferred_location_id
 * @property int|null $preferred_dentist_id
 * @property PatientStatus $status
 * @property string $outstanding_balance
 * @property string $outstanding_balance_currency
 * @property string|null $medical_notes
 * @property array<string>|null $allergies
 * @property array<string>|null $medications
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relationship
 * @property string|null $insurance_provider
 * @property string|null $insurance_policy_number
 * @property string|null $insurance_group_number
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read User|null $preferredDentist
 * @property-read string $full_name
 * @property-read string $display_name
 * @property-read int|null $age
 */
class Patient extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\PatientFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'preferred_name',
        'date_of_birth',
        'gender',
        'phone',
        'phone_secondary',
        'email',
        'preferred_contact_method',
        'contact_consent',
        'marketing_consent',
        'address',
        'preferred_location_id',
        'preferred_dentist_id',
        'status',
        'outstanding_balance',
        'outstanding_balance_currency',
        'medical_notes',
        'allergies',
        'medications',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'insurance_provider',
        'insurance_policy_number',
        'insurance_group_number',
        'notes',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'full_name',
        'display_name',
        'age',
    ];

    /**
     * Get activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['medical_notes', 'allergies', 'medications']); // Exclude PHI from logs
    }

    /**
     * Get the preferred dentist for this patient.
     *
     * @return BelongsTo<User, $this>
     */
    public function preferredDentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_dentist_id');
    }

    /**
     * Scope to only include active patients.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Patient>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<Patient>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', PatientStatus::Active->value);
    }

    /**
     * Scope to search patients using full-text search.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Patient>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<Patient>
     */
    public function scopeSearch(\Illuminate\Database\Eloquent\Builder $query, string $term): \Illuminate\Database\Eloquent\Builder
    {
        // Sanitize the search term
        $sanitized = preg_replace('/[^\w\s]/', '', $term);

        if (empty($sanitized)) {
            return $query;
        }

        // Use PostgreSQL full-text search
        return $query->whereRaw(
            'search_vector @@ plainto_tsquery(\'english\', ?)',
            [$sanitized],
        )->orderByRaw(
            'ts_rank(search_vector, plainto_tsquery(\'english\', ?)) DESC',
            [$sanitized],
        );
    }

    /**
     * Scope to filter by outstanding balance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Patient>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<Patient>
     */
    public function scopeWithOutstandingBalance(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('outstanding_balance', '>', 0);
    }

    /**
     * Check if the patient is a minor (under 18).
     */
    public function isMinor(): bool
    {
        $age = $this->age;

        return $age !== null && $age < 18;
    }

    /**
     * Check if the patient has an outstanding balance.
     */
    public function hasOutstandingBalance(): bool
    {
        return (float) $this->outstanding_balance > 0;
    }

    /**
     * Get the formatted address as a string.
     */
    public function getFormattedAddress(): ?string
    {
        if (! $this->address) {
            return null;
        }

        $parts = array_filter([
            $this->address['street'] ?? null,
            $this->address['city'] ?? null,
            $this->address['state'] ?? null,
            $this->address['postal_code'] ?? null,
            $this->address['country'] ?? null,
        ]);

        return empty($parts) ? null : implode(', ', $parts);
    }

    /**
     * Get the patient's full name.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $parts = array_filter([
                    $this->first_name,
                    $this->middle_name,
                    $this->last_name,
                ]);

                return implode(' ', $parts);
            },
        );
    }

    /**
     * Get the patient's display name (preferred name or full name).
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->preferred_name) {
                    return $this->preferred_name.' '.$this->last_name;
                }

                return $this->full_name;
            },
        );
    }

    /**
     * Get the patient's age in years.
     *
     * @return Attribute<int|null, never>
     */
    protected function age(): Attribute
    {
        return Attribute::make(
            get: function (): ?int {
                if (! $this->date_of_birth) {
                    return null;
                }

                return $this->date_of_birth->age;
            },
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'preferred_contact_method' => ContactMethod::class,
            'contact_consent' => 'boolean',
            'marketing_consent' => 'boolean',
            'address' => 'array',
            'status' => PatientStatus::class,
            'outstanding_balance' => 'decimal:2',
            'allergies' => 'array',
            'medications' => 'array',
        ];
    }
}
