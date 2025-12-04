<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\DefinesPermissions;
use App\Enums\ContactMethod;
use App\Enums\Gender;
use App\Enums\PatientStatus;
use App\Traits\BelongsToTenant;
use App\Traits\HasModelPermissions;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string|null $preferred_name
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property Gender|null $gender
 * @property string|null $phone
 * @property string|null $phone_secondary
 * @property string|null $email
 * @property ContactMethod $preferred_contact_method
 * @property bool $contact_consent
 * @property bool $marketing_consent
 * @property array<string, mixed>|null $address
 * @property string|null $preferred_location_id
 * @property string|null $preferred_dentist_id
 * @property PatientStatus $status
 * @property float $outstanding_balance
 * @property string $outstanding_balance_currency
 * @property array<string, mixed>|null $medical_alerts
 * @property array<string, mixed>|null $insurance_info
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read User|null $preferredDentist
 * @property-read string $name
 * @property-read string $full_name
 * @property-read int|null $age
 */
class Patient extends Model implements DefinesPermissions
{
    use BelongsToTenant;
    use HasFactory;
    use HasModelPermissions;
    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
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
        'medical_alerts',
        'insurance_info',
        'notes',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['name', 'full_name', 'age'];

    /**
     * Get activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the preferred dentist for the patient.
     *
     * @return BelongsTo<User, $this>
     */
    public function preferredDentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_dentist_id');
    }

    /**
     * Check if patient is active.
     */
    public function isActive(): bool
    {
        return $this->status === PatientStatus::Active;
    }

    /**
     * Check if patient has any medical alerts.
     */
    public function hasMedicalAlerts(): bool
    {
        return ! empty($this->medical_alerts);
    }

    /**
     * Check if patient has outstanding balance.
     */
    public function hasOutstandingBalance(): bool
    {
        return $this->outstanding_balance > 0;
    }

    /**
     * Get formatted address as a single string.
     */
    public function getFormattedAddress(): ?string
    {
        if (empty($this->address)) {
            return null;
        }

        $parts = array_filter([
            $this->address['street'] ?? null,
            $this->address['city'] ?? null,
            $this->address['state'] ?? null,
            $this->address['postal_code'] ?? null,
            $this->address['country'] ?? null,
        ]);

        return implode(', ', $parts) ?: null;
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
     * Scope to include patients with outstanding balance.
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
     * Scope to search patients by name, phone, or email.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Patient>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<Patient>
     */
    public function scopeSearch(\Illuminate\Database\Eloquent\Builder $query, string $term): \Illuminate\Database\Eloquent\Builder
    {
        $term = '%'.strtolower($term).'%';

        return $query->where(function ($q) use ($term): void {
            $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                ->orWhere('phone', 'like', $term);
        });
    }

    /**
     * Get the patient's full name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(
                $this->first_name.' '.
                ($this->middle_name ? $this->middle_name.' ' : '').
                $this->last_name,
            ),
        );
    }

    /**
     * Get the patient's display name (preferred or first + last).
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->preferred_name
                ?? trim($this->first_name.' '.$this->last_name),
        );
    }

    /**
     * Get the patient's age.
     */
    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->date_of_birth?->age,
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
            'medical_alerts' => 'array',
            'insurance_info' => 'array',
        ];
    }
}
