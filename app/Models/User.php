<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\DefinesPermissions;
use App\Enums\UserRole;
use App\Traits\BelongsToTenant;
use App\Traits\HasModelPermissions;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $title
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property UserRole $primary_role
 * @property string|null $phone
 * @property string|null $license_number
 * @property string|null $specialization
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property array<string, mixed>|null $preferences
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Tenant|null $tenant
 * @property-read string $name
 * @property-read string $full_name
 */
class User extends Authenticatable implements DefinesPermissions
{
    use BelongsToTenant;
    use HasApiTokens;
    use HasFactory;
    use HasModelPermissions;
    use HasRoles;
    use HasUuids;
    use LogsActivity;
    use Notifiable;
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
        'title',
        'email',
        'password',
        'primary_role',
        'phone',
        'license_number',
        'specialization',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['name', 'full_name'];

    /**
     * Get activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['password', 'remember_token']);
    }

    /**
     * Check if user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->primary_role === UserRole::Admin;
    }

    /**
     * Check if user is a provider (dentist or hygienist).
     */
    public function isProvider(): bool
    {
        return $this->primary_role->isProvider();
    }

    /**
     * Check if user is a dentist.
     */
    public function isDentist(): bool
    {
        return $this->primary_role === UserRole::Dentist;
    }

    /**
     * Check if user belongs to a specific tenant.
     */
    public function belongsToTenant(string $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }

    /**
     * Update last login timestamp and IP.
     */
    public function updateLastLogin(?string $ipAddress = null): bool
    {
        return $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);
    }

    /**
     * Get a preference value by key.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Update a single preference.
     */
    public function updatePreference(string $key, mixed $value): bool
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);

        return $this->update(['preferences' => $preferences]);
    }

    /**
     * Scope to only include active users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only include providers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeProviders(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('primary_role', [
            UserRole::Dentist->value,
            UserRole::Hygienist->value,
        ]);
    }

    /**
     * Scope to filter by role.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeRole(\Illuminate\Database\Eloquent\Builder $query, UserRole $role): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('primary_role', $role->value);
    }

    /**
     * Get the user's full name (with title).
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(($this->title ? $this->title.' ' : '').$this->first_name.' '.$this->last_name),
        );
    }

    /**
     * Get the user's name (first + last).
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim($this->first_name.' '.$this->last_name),
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'primary_role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'preferences' => 'array',
        ];
    }
}
