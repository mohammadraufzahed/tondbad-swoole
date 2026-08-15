<?php

declare(strict_types=1);

use OpenSwoole\Http\Request as SwooleRequest;
use TondbadSwoole\Http\FormRequest;
use TondbadSwoole\Validation\Attributes\Field;
use TondbadSwoole\Validation\ValidationException;

final class StoreUserRequest extends FormRequest
{
    #[Field(alias: 'email_address', transform: 'trim|strtolower', rules: 'email')]
    public readonly string $email;

    #[Field(rules: 'min:8')]
    public readonly string $password;

    #[Field(default: 18, rules: 'gte:0')]
    public readonly int $age;
}

it('hydrates a form request from #[Field] attributes', function () {
    $swoole = new SwooleRequest();
    $swoole->post = [
        'email_address' => '  User@Example.COM ',
        'password' => 'password123',
    ];
    $swoole->get = [];
    $swoole->server = [];
    $swoole->header = [];
    $swoole->cookie = [];

    $request = new StoreUserRequest($swoole);

    expect($request->validated())->toBe($request)
        ->and($request->email)->toBe('user@example.com')
        ->and($request->password)->toBe('password123')
        ->and($request->age)->toBe(18);
});

it('throws ValidationException when attribute validation fails', function () {
    $swoole = new SwooleRequest();
    $swoole->post = [
        'email_address' => 'not-an-email',
        'password' => 'password123',
    ];
    $swoole->get = [];
    $swoole->server = [];
    $swoole->header = [];
    $swoole->cookie = [];

    new StoreUserRequest($swoole);
})->throws(ValidationException::class);
