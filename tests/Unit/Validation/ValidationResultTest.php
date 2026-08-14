<?php

declare(strict_types=1);

use TondbadSwoole\Validation\ValidationException;
use TondbadSwoole\Validation\ValidationResult;

it('represents a successful validation', function () {
    $result = new ValidationResult(true, ['name' => 'Ali'], []);

    expect($result->valid)->toBeTrue()
        ->and($result->data)->toBe(['name' => 'Ali'])
        ->and($result->orFail())->toBe(['name' => 'Ali']);
});

it('throws on failed validation', function () {
    $result = new ValidationResult(false, null, [
        ['field' => 'email', 'rule' => 'email', 'message' => 'Invalid email', 'params' => []],
    ]);

    expect(fn () => $result->orFail())->toThrow(ValidationException::class);
});
