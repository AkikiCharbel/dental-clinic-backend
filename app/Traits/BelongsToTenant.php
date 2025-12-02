<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for models that belong to a tenant.
 *
 * This trait automatically scopes queries to the current tenant
 * and sets the tenant_id when creating new models.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    /**
     * Query without tenant scope.
     *
     * @return Builder<static>
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }

    /**
     * Get the tenant that owns the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Tenant, $this>
     */
    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /**
     * Boot the trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Automatically scope queries to the current tenant
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = tenant_id();

            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        // Automatically set tenant_id when creating a new model
        static::creating(function (Model $model): void {
            $tenantId = tenant_id();

            if ($tenantId !== null && ! $model->getAttribute('tenant_id')) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }
}
