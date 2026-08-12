<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Address;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Company;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new App(__DIR__ . '/../../../..');

    schema()->create('companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('address_street')->nullable();
        $table->string('address_city')->nullable();
    });
});

afterEach(function () {
    schema()->dropIfExists('companies');

    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('persists and hydrates an embedded value object', function () {
    $company = Company::create([
        'name' => 'Acme',
        'address' => [
            'street' => '123 Main St',
            'city' => 'Springfield',
        ],
    ]);

    expect($company->address)->toBeInstanceOf(Address::class);
    expect($company->address->street)->toBe('123 Main St');
    expect($company->address->city)->toBe('Springfield');

    $found = Company::find($company->id);

    expect($found->address)->toBeInstanceOf(Address::class);
    expect($found->address->street)->toBe('123 Main St');
    expect($found->address->city)->toBe('Springfield');
});

it('updates embedded columns through the model', function () {
    $company = Company::create([
        'name' => 'Acme',
        'address' => [
            'street' => 'Old Rd',
            'city' => 'Oldtown',
        ],
    ]);

    $company->address = new Address(street: 'New Ave', city: 'Newcity');
    $company->save();

    $fresh = Company::find($company->id);

    expect($fresh->address->street)->toBe('New Ave');
    expect($fresh->address->city)->toBe('Newcity');
});
