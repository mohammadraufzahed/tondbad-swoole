<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Field
{
    /**
     * @param string|null $alias
     * @param string|null $rules
     * @param mixed $default
     * @param string|null $nested
     * @param string|null $transform
     */
    public function __construct(
        public readonly ?string $alias = null,
        public readonly ?string $rules = null,
        public readonly mixed $default = null,
        public readonly ?string $nested = null,
        public readonly ?string $transform = null,
    ) {
    }
}
