<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Schema;

class ForeignKey
{
    public array $references = [];

    public string $on = '';

    public ?string $onDelete = null;

    public ?string $onUpdate = null;

    public function __construct(
        public string $name,
        public array $columns,
    ) {
    }

    public function references(string|array $columns): self
    {
        $this->references = is_array($columns) ? $columns : [$columns];

        return $this;
    }

    public function on(string $table): self
    {
        $this->on = $table;

        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;

        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;

        return $this;
    }
}
