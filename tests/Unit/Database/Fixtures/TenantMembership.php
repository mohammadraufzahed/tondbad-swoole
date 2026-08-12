<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Model;

class TenantMembership extends Model
{
    protected ?string $table = 'tenant_memberships';

    protected string|array $primaryKey = ['tenant_id', 'user_id'];

    protected bool $incrementing = false;

    protected string|array $keyType = ['int', 'int'];

    public bool $timestamps = false;

    protected array $fillable = ['tenant_id', 'user_id', 'role'];
}
