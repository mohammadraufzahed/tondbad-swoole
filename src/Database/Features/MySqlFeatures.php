<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Features;

class MySqlFeatures extends AbstractFeatures
{
    public function supportsSavepoints(): bool
    {
        return true;
    }

    public function hasNativeJsonField(): bool
    {
        return true;
    }

    public function supportsEngineClause(): bool
    {
        return true;
    }

    public function supportsUnsignedModifier(): bool
    {
        return true;
    }

    public function supportsAutoIncrement(): bool
    {
        return true;
    }

    public function supportsColumnComments(): bool
    {
        return true;
    }
}
