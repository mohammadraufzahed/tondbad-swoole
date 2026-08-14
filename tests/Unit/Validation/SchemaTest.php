<?php

declare(strict_types=1);

use TondbadSwoole\Validation\Schema;
use TondbadSwoole\Validation\ValidationException;

it('parses string values', function () {
    $schema = Schema::string()->min(3)->max(10);

    expect($schema->parse('hello'))->toBe('hello');

    $result = $schema->safeParse('hi');
    expect($result->valid)->toBeFalse();
    expect($result->errors[0]['rule'])->toBe('min');
});

it('parses integer values with coercion', function () {
    $schema = Schema::int()->coerce()->gte(0)->lte(120);

    expect($schema->parse('42'))->toBe(42);

    $result = $schema->safeParse('150');
    expect($result->valid)->toBeFalse();
    expect($result->errors[0]['rule'])->toBe('lte');
});

it('validates email addresses', function () {
    $schema = Schema::string()->email();

    expect($schema->parse('user@example.com'))->toBe('user@example.com');

    $result = $schema->safeParse('not-an-email');
    expect($result->valid)->toBeFalse();
});

it('applies defaults for missing object keys', function () {
    $schema = Schema::object([
        'name' => Schema::string()->required(),
        'age' => Schema::int()->default(18),
    ])->lax();

    expect($schema->parse(['name' => 'Ali']))->toBe([
        'name' => 'Ali',
        'age' => 18,
    ]);
});

it('rejects extra keys in strict mode', function () {
    $schema = Schema::object(['name' => Schema::string()])->strict();

    $result = $schema->safeParse(['name' => 'Ali', 'extra' => 'value']);

    expect($result->valid)->toBeFalse();
    expect($result->errors[0]['rule'])->toBe('strict');
});

it('validates arrays of strings', function () {
    $schema = Schema::array(Schema::string())->min(1)->max(3);

    expect($schema->parse(['a', 'b']))->toBe(['a', 'b']);

    $result = $schema->safeParse([]);
    expect($result->valid)->toBeFalse();
    expect($result->errors[0]['rule'])->toBe('min');
});

it('allows nullable values', function () {
    $schema = Schema::string()->nullable();

    expect($schema->parse(null))->toBeNull();
});

it('coerces boolean strings in lax mode', function () {
    $schema = Schema::bool()->lax();

    expect($schema->parse('true'))->toBeTrue();
    expect($schema->parse('0'))->toBeFalse();
});

it('parses json strings', function () {
    $schema = Schema::json();

    expect($schema->parse('{"a":1}'))->toBe(['a' => 1]);
});

it('supports union enums', function () {
    $schema = Schema::enum('admin', 'editor');

    expect($schema->parse('editor'))->toBe('editor');

    $result = $schema->safeParse('guest');
    expect($result->valid)->toBeFalse();
});

it('applies transforms', function () {
    $schema = Schema::string()->transform('trim')->transform('strtolower');

    expect($schema->parse('  HELLO  '))->toBe('hello');
});

it('validates nested objects', function () {
    $schema = Schema::object([
        'user' => Schema::object([
            'email' => Schema::string()->email(),
        ])->lax(),
    ])->lax();

    $result = $schema->safeParse(['user' => ['email' => 'invalid']]);

    expect($result->valid)->toBeFalse();
    expect($result->errors[0]['field'])->toBe('user.email');
});

it('throws on invalid parse', function () {
    $schema = Schema::int();

    $schema->parse('not-int');
})->throws(ValidationException::class);

it('supports custom refinements', function () {
    $schema = Schema::int()->refine(fn (int $v) => $v % 2 === 0, 'Value must be even');

    expect($schema->parse(4))->toBe(4);

    $result = $schema->safeParse(3);
    expect($result->valid)->toBeFalse();
    expect($result->errors[0]['rule'])->toBe('refine');
});
