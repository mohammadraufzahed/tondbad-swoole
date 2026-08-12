<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Features;

use TondbadSwoole\Database\Contracts\DatabaseFeatures;

abstract class AbstractFeatures implements DatabaseFeatures
{
    public function supportsTransactions(): bool
    {
        return true;
    }

    public function supportsSavepoints(): bool
    {
        return false;
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    public function supportsDeferrableConstraints(): bool
    {
        return false;
    }

    public function hasNativeJsonField(): bool
    {
        return false;
    }

    public function supportsEngineClause(): bool
    {
        return false;
    }

    public function supportsUnsignedModifier(): bool
    {
        return false;
    }

    public function supportsAutoIncrement(): bool
    {
        return false;
    }

    public function supportsGeneratedAsIdentity(): bool
    {
        return false;
    }

    public function supportsColumnComments(): bool
    {
        return false;
    }
}
