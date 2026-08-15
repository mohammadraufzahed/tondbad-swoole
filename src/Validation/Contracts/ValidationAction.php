<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Contracts;

use TondbadSwoole\Validation\ValidationContext;

interface ValidationAction
{
    public function validate(mixed $value, ValidationContext $ctx): mixed;
}
