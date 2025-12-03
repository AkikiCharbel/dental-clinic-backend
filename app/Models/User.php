<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $title
 * @property UserRole $primary_role
 * @property string $email
 * @property string|null $phone
 * @property string|null $license_number
 * @property string|null $specialization
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property array<string, mixed>|null $preferences
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Tenant|null $tenant
 * @property-read string $full_name
 */
class User extends Authenticatable
{
    use BelongsToTenant;
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasRoles;
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
        'name',
        'first_name',
        'last_name',
        'title',
        'primary_role',
        'email',
        'phone',
        'license_number',
        'specialization',
        'password',
        'preferences',
        'is_active',
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
    protected $appends = [
        'full_name',
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
            ->logExcept(['password', 'remember_token']);
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
     * Scope to filter by role.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeWithRole(\Illuminate\Database\Eloquent\Builder $query, UserRole $role): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('primary_role', $role->value);
    }

    /**
     * Scope to filter providers (dentists and hygienists).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeProviders(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('primary_role', [UserRole::Dentist->value, UserRole::Hygienist->value]);
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->primary_role === UserRole::Admin;
    }

    /**
     * Check if the user is a dentist.
     */
    public function isDentist(): bool
    {
        return $this->primary_role === UserRole::Dentist;
    }

    /**
     * Check if the user is a provider (dentist or hygienist).
     */
    public function isProvider(): bool
    {
        return $this->primary_role->isProvider();
    }

    /**
     * Check if the user belongs to a specific tenant.
     */
    public function belongsToTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }

    /**
     * Update the user's last login information.
     */
    public function updateLastLogin(?string $ipAddress = null): bool
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ipAddress;

        return $this->save();
    }

    /**
     * Get a preference value by key.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        $preferences = $this->preferences ?? [];

        return $preferences[$key] ?? $default;
    }

    /**
     * Update a single preference value.
     */
    public function updatePreference(string $key, mixed $value): bool
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;
        $this->preferences = $preferences;

        return $this->save();
    }

    /**
     * Get the user's full name.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->first_name || $this->last_name) {
                    $parts = array_filter([$this->title, $this->first_name, $this->last_name]);

                    return implode(' ', $parts);
                }

                return $this->name;
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
            'primary_role' => UserRole::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
