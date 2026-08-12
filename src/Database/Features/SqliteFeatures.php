<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Features;

class SqliteFeatures extends AbstractFeatures
{
    public function hasNativeJsonField(): bool
    {
        return true;
    }

    public function supportsAutoIncrement(): bool
    {
        return true;
    }
}
