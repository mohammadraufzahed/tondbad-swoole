<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Contracts;

interface DatabaseFeatures
{
    public function supportsTransactions(): bool;

    public function supportsSavepoints(): bool;

    public function supportsReturning(): bool;

    public function supportsForUpdateSkipLocked(): bool;

    public function supportsDeferrableConstraints(): bool;

    public function hasNativeJsonField(): bool;

    public function supportsEngineClause(): bool;

    public function supportsUnsignedModifier(): bool;

    public function supportsAutoIncrement(): bool;

    public function supportsGeneratedAsIdentity(): bool;

    public function supportsColumnComments(): bool;
}
