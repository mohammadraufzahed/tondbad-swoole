<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\ValidationException;
use TondbadSwoole\Validation\Validator;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_validation_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->config = $this->app->container->make(Config::class);
    $this->config->set('database.default', 'sqlite');
    $this->config->set('database.connections.sqlite.database', ':memory:');

    $this->manager = $this->app->container->make(DatabaseManager::class);
    $this->manager->connection()->getSchemaBuilder()->create('users', function ($table): void {
        $table->id();
        $table->string('email')->unique();
        $table->string('name');
        $table->integer('role_id')->nullable();
    });
});

it('passes when required fields are present', function () {
    $validator = new Validator(['email' => 'test@example.com'], ['email' => 'required|email']);

    expect($validator->passes())->toBeTrue();
    expect($validator->validated())->toBe(['email' => 'test@example.com']);
});

it('fails when required fields are missing', function () {
    $validator = new Validator([], ['email' => 'required|email']);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors())->toHaveKey('email');
});

it('validates min and max rules', function () {
    $validator = new Validator(
        ['name' => 'ab', 'age' => '150'],
        ['name' => 'required|min:3', 'age' => 'required|int|max:120'],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors())->toHaveKey('name')
        ->and($validator->errors())->toHaveKey('age');
});

it('validates in and not in rules', function () {
    $validator = new Validator(
        ['role' => 'admin', 'status' => 'banned'],
        ['role' => 'in:admin,editor', 'status' => 'not_in:banned,deleted'],
    );

    expect($validator->passes())->toBeFalse();
    expect($validator->errors())->toHaveKey('status');
});

it('respects nullable and sometimes rules', function () {
    $validator = new Validator(
        ['bio' => null],
        ['bio' => 'sometimes|nullable|string|min:10', 'nickname' => 'sometimes|required'],
    );

    expect($validator->passes())->toBeTrue();
    expect($validator->validated())->toBe(['bio' => null]);
});

it('validates confirmed and same rules', function () {
    $validator = new Validator(
        ['password' => 'secret', 'password_confirmation' => 'secret', 'repeat' => 'secret'],
        ['password' => 'required|confirmed', 'repeat' => 'same:password'],
    );

    expect($validator->passes())->toBeTrue();
});

it('validates json and uuid rules', function () {
    $validator = new Validator(
        ['payload' => '{"a":1}', 'id' => 'invalid-uuid'],
        ['payload' => 'required|json', 'id' => 'required|uuid'],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors())->toHaveKey('id');
});

it('throws ValidationException with errors', function () {
    $validator = new Validator(['email' => 'not-an-email'], ['email' => 'required|email']);

    $validator->validated();
})->throws(ValidationException::class);

it('validates unique against the database', function () {
    $this->manager->table('users')->insert(['email' => 'taken@example.com', 'name' => 'Jane', 'role_id' => 1]);

    $validator = new Validator(
        ['email' => 'taken@example.com'],
        ['email' => 'required|email|unique:users,email'],
        databaseManager: $this->manager,
    );

    expect($validator->fails())->toBeTrue();
});

it('validates exists against the database', function () {
    $this->manager->table('users')->insert(['email' => 'exists@example.com', 'name' => 'John', 'role_id' => 2]);

    $validator = new Validator(
        ['role_id' => 2],
        ['role_id' => 'required|int|exists:users,role_id'],
        databaseManager: $this->manager,
    );

    expect($validator->passes())->toBeTrue();
});

it('uses custom error messages', function () {
    $validator = new Validator(
        ['email' => ''],
        ['email' => 'required|email'],
        ['email.required' => 'We need your email.'],
    );

    expect($validator->errors()['email'][0])->toBe('We need your email.');
});
