<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\DefinesPermissions;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Traits\HasModelPermissions;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $country_code
 * @property SubscriptionStatus $subscription_status
 * @property SubscriptionPlan $subscription_plan
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $subscription_ends_at
 * @property string $default_currency
 * @property string $timezone
 * @property string $locale
 * @property array<string, mixed>|null $features
 * @property array<string, mixed>|null $settings
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Patient> $patients
 */
class Tenant extends Model implements DefinesPermissions
{
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
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'country_code',
        'subscription_status',
        'subscription_plan',
        'trial_ends_at',
        'subscription_ends_at',
        'default_currency',
        'timezone',
        'locale',
        'features',
        'settings',
        'is_active',
    ];

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
     * Get the users for the tenant.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the patients for the tenant.
     *
     * @return HasMany<Patient, $this>
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    /**
     * Check if tenant is active and subscription allows access.
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->subscription_status->allowsAccess();
    }

    /**
     * Check if tenant is currently on trial.
     */
    public function isOnTrial(): bool
    {
        if ($this->subscription_status !== SubscriptionStatus::Trial) {
            return false;
        }

        if ($this->trial_ends_at === null) {
            return true;
        }

        return $this->trial_ends_at->isFuture();
    }

    /**
     * Check if trial has expired.
     */
    public function hasTrialExpired(): bool
    {
        if ($this->subscription_status !== SubscriptionStatus::Trial) {
            return false;
        }

        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    /**
     * Check if subscription has expired.
     */
    public function hasSubscriptionExpired(): bool
    {
        if ($this->subscription_ends_at === null) {
            return false;
        }

        return $this->subscription_ends_at->isPast();
    }

    /**
     * Get a setting value by key.
     */
    public function getSettingValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Update a single setting.
     */
    public function updateSetting(string $key, mixed $value): bool
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);

        return $this->update(['settings' => $settings]);
    }

    /**
     * Check if a feature is enabled.
     */
    public function hasFeature(string $feature): bool
    {
        return (bool) data_get($this->features, $feature, false);
    }

    /**
     * Get remaining trial days.
     */
    public function trialDaysRemaining(): int
    {
        if (! $this->isOnTrial() || $this->trial_ends_at === null) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }

    /**
     * Check if tenant can add more users based on plan limits.
     */
    public function canAddUser(): bool
    {
        return $this->users()->count() < $this->subscription_plan->maxUsers();
    }

    /**
     * Scope to only include active tenants.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Tenant>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<Tenant>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true)
            ->whereIn('subscription_status', [
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Active->value,
                SubscriptionStatus::PastDue->value,
            ]);
    }

    /**
     * Scope to only include tenants on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Tenant>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<Tenant>
     */
    public function scopeOnTrial(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('subscription_status', SubscriptionStatus::Trial->value)
            ->where(function ($q): void {
                $q->whereNull('trial_ends_at')
                    ->orWhere('trial_ends_at', '>', now());
            });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_status' => SubscriptionStatus::class,
            'subscription_plan' => SubscriptionPlan::class,
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'features' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
