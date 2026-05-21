<?php

namespace App\Models\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        // Strict mode: throw if no active tenant. Callers that legitimately need
        // cross-tenant access must opt out via withoutGlobalScope(TenantScope::class).
        $tenantId = $context->id();

        $builder->where("{$model->getTable()}.tenant_id", $tenantId);
    }

    /**
     * @param  Builder<Model>  $builder
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenantScope', function (Builder $builder): Builder {
            return $builder->withoutGlobalScope(self::class);
        });
    }
}
