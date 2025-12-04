<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property SubscriptionStatus $subscription_status
 * @property SubscriptionPlan $subscription_plan
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $subscription_ends_at
 * @property string $default_currency
 * @property string $timezone
 * @property string $locale
 * @property string|null $country_code
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $features
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;
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
        'subscription_status',
        'subscription_plan',
        'trial_ends_at',
        'subscription_ends_at',
        'default_currency',
        'timezone',
        'locale',
        'country_code',
        'settings',
        'features',
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
     * Scope to only include active tenants.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Tenant>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<Tenant>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if the tenant is currently active and can access the system.
     */
    public function isAccessible(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->subscription_status->isAccessible();
    }

    /**
     * Check if the tenant is on a trial.
     */
    public function isOnTrial(): bool
    {
        return $this->subscription_status === SubscriptionStatus::Trial
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    /**
     * Check if the trial has expired.
     */
    public function isTrialExpired(): bool
    {
        return $this->subscription_status === SubscriptionStatus::Trial
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }

    /**
     * Check if the subscription has expired.
     */
    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_ends_at !== null
            && $this->subscription_ends_at->isPast();
    }

    /**
     * Get a setting value by key with optional default.
     */
    public function getSettingValue(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];

        return $settings[$key] ?? $default;
    }

    /**
     * Update a single setting value.
     */
    public function updateSetting(string $key, mixed $value): bool
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;

        return $this->save();
    }

    /**
     * Update multiple settings at once.
     *
     * @param  array<string, mixed>  $newSettings
     */
    public function updateSettings(array $newSettings): bool
    {
        $settings = $this->settings ?? [];
        $this->settings = array_merge($settings, $newSettings);

        return $this->save();
    }

    /**
     * Check if a feature is enabled for this tenant.
     */
    public function hasFeature(string $feature): bool
    {
        // First check tenant-specific features
        $features = $this->features ?? [];
        if (isset($features[$feature])) {
            return (bool) $features[$feature];
        }

        // Fall back to plan defaults
        $planFeatures = $this->subscription_plan->defaultFeatures();

        return (bool) ($planFeatures[$feature] ?? false);
    }

    /**
     * Get a feature value (for numeric features like max_users).
     */
    public function getFeatureValue(string $feature, mixed $default = null): mixed
    {
        // First check tenant-specific features
        $features = $this->features ?? [];
        if (isset($features[$feature])) {
            return $features[$feature];
        }

        // Fall back to plan defaults
        $planFeatures = $this->subscription_plan->defaultFeatures();

        return $planFeatures[$feature] ?? $default;
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
            'settings' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
