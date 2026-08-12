<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\EntityEvent;
use TondbadSwoole\Database\EntityEventSubscriber;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Tests\Unit\Database\Fixtures\EventedUser;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new App(__DIR__ . '/../../../..');

    schema()->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->json('settings')->nullable();
        $table->boolean('is_admin')->default(false);
        $table->timestamps();
    });
});

afterEach(function () {
    schema()->dropIfExists('users');

    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('fires entity lifecycle hooks on create, update, and delete', function () {
    $user = EventedUser::create([
        'name' => 'Hook',
        'email' => 'hook@example.com',
    ]);

    expect($user->lifecycleEvents)->toContain('onCreate');
    expect($user->lifecycleEvents)->toContain('onFlush');

    $user->name = 'Updated';
    $user->save();

    expect($user->lifecycleEvents)->toContain('onUpdate');

    $user->delete();

    expect($user->lifecycleEvents)->toContain('onDelete');
});

it('fires postLoad when an entity is loaded from the database', function () {
    EventedUser::create([
        'name' => 'Loader',
        'email' => 'loader@example.com',
    ]);

    em()->clear();
    $loaded = em()->find(EventedUser::class, 1);

    expect($loaded->lifecycleEvents)->toContain('onLoad');
});

it('allows external subscribers to listen to entity events', function () {
    $subscriber = new class implements EntityEventSubscriber {
        public array $events = [];

        public function getSubscribedEvents(): array
        {
            return [
                'postPersist' => 'onPostPersist',
                'postUpdate' => 'onPostUpdate',
            ];
        }

        public function onPostPersist(EntityEvent $event): void
        {
            $this->events[] = 'postPersist';
        }

        public function onPostUpdate(EntityEvent $event): void
        {
            $this->events[] = 'postUpdate';
        }
    };

    em()->getEventManager()->addEventSubscriber($subscriber);

    $user = EventedUser::create([
        'name' => 'Sub',
        'email' => 'sub@example.com',
    ]);

    $user->name = 'Updated';
    $user->save();

    expect($subscriber->events)->toContain('postPersist');
    expect($subscriber->events)->toContain('postUpdate');
});

it('allows direct event listeners', function () {
    $events = [];

    em()->getEventManager()->addEventListener('postPersist', function (EntityEvent $event) use (&$events) {
        $events[] = $event->event;
    });

    EventedUser::create([
        'name' => 'Listener',
        'email' => 'listener@example.com',
    ]);

    expect($events)->toContain('postPersist');
});
