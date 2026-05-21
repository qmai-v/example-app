<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantScopedTestModel extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_scoped_test_models';

    protected $guarded = [];

    public $timestamps = true;
}
