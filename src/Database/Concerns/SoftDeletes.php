<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Concerns;

use TondbadSwoole\Database\Scopes\SoftDeleteScope;

trait SoftDeletes
{
    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeleteScope());
    }

    public function usesSoftDeletes(): bool
    {
        return true;
    }

    public function forceDelete(): bool
    {
        return $this->performDelete() > 0;
    }

    public function restore(): bool
    {
        $this->{$this->getDeletedAtColumn()} = null;

        return $this->save();
    }

    public function trashed(): bool
    {
        return $this->{$this->getDeletedAtColumn()} !== null;
    }

    public function getDeletedAtColumn(): string
    {
        return 'deleted_at';
    }

    protected function softDelete(): int
    {
        $this->{$this->getDeletedAtColumn()} = $this->freshTimestampString();

        return $this->save() ? 1 : 0;
    }
}
