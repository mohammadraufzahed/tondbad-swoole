<?php

declare(strict_types=1);

use TondbadSwoole\Http\FormRequest;
use TondbadSwoole\Validation\ValidationException;

it('validates a form request on creation', function () {
    $swooleRequest = new OpenSwoole\Http\Request();
    $swooleRequest->get = [];
    $swooleRequest->post = ['email' => 'test@example.com', 'name' => 'John'];
    $swooleRequest->server = [];

    $request = new class($swooleRequest) extends FormRequest {
        public function rules(): array
        {
            return [
                'email' => 'required|email',
                'name' => 'required|min:2',
            ];
        }
    };

    expect($request->validated())->toBe([
        'email' => 'test@example.com',
        'name' => 'John',
    ]);
});

it('throws when form request validation fails', function () {
    $swooleRequest = new OpenSwoole\Http\Request();
    $swooleRequest->get = [];
    $swooleRequest->post = ['email' => 'invalid'];
    $swooleRequest->server = [];

    new class($swooleRequest) extends FormRequest {
        public function rules(): array
        {
            return ['email' => 'required|email'];
        }
    };
})->throws(ValidationException::class);
