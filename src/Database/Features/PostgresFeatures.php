<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Features;

class PostgresFeatures extends AbstractFeatures
{
    public function supportsSavepoints(): bool
    {
        return true;
    }

    public function supportsReturning(): bool
    {
        return true;
    }

    public function supportsDeferrableConstraints(): bool
    {
        return true;
    }

    public function hasNativeJsonField(): bool
    {
        return true;
    }

    public function supportsGeneratedAsIdentity(): bool
    {
        return true;
    }
}
