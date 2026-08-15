<?php

declare(strict_types=1);

use TondbadSwoole\Validation\Attributes\Field;
use TondbadSwoole\Validation\DtoFactory;

final class AddressDto {
    public function __construct(
        #[Field(alias: 'zip_code')]
        public readonly string $zip,
        #[Field(default: 'US')]
        public readonly string $country,
    ) {}
}

final class ProfileDto {
    public function __construct(
        #[Field(alias: 'email_address', transform: 'trim|strtolower', rules: 'email')]
        public readonly string $email,
        #[Field(rules: 'gte:0', default: 18)]
        public readonly int $age,
        #[Field(nested: AddressDto::class)]
        public readonly AddressDto $address,
    ) {}
}

it('builds a DTO from attributes', function () {
    $dto = DtoFactory::make(ProfileDto::class, [
        'email_address' => '  User@Example.COM ',
        'address' => ['zip_code' => '12345'],
    ]);

    expect($dto)->toBeInstanceOf(ProfileDto::class)
        ->and($dto->email)->toBe('user@example.com')
        ->and($dto->age)->toBe(18)
        ->and($dto->address->zip)->toBe('12345')
        ->and($dto->address->country)->toBe('US');
});

it('fails with structured errors for invalid DTO data', function () {
    $schema = DtoFactory::schema(ProfileDto::class, false);

    $result = $schema->safeParse(['email_address' => 'not-an-email']);

    expect($result->valid)->toBeFalse();
    expect(array_column($result->errors, 'field'))->toContain('email');
});

it('generates a schema from a DTO class', function () {
    $schema = DtoFactory::schema(ProfileDto::class, false);

    expect($schema->getType())->toBe('object');
});
