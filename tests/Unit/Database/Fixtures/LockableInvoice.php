<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Attributes\Version;
use TondbadSwoole\Database\Model;

class LockableInvoice extends Model
{
    protected ?string $table = 'lockable_invoices';

    public bool $timestamps = false;

    protected array $fillable = ['amount', 'version'];

    #[Version]
    protected int $version = 0;
}
