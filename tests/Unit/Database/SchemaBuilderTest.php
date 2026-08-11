<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\DatabaseManager;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_schema_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->config = $this->app->container->make(Config::class);
    $this->config->set('database.default', 'sqlite');
    $this->config->set('database.connections.sqlite.database', ':memory:');

    $this->schema = $this->app->container->make(DatabaseManager::class)
        ->connection()
        ->getSchemaBuilder();
});

afterEach(function () {
    $this->schema = null;
    $this->app = null;
});

it('creates and drops a table with sqlite', function () {
    $this->schema->create('users', function ($table): void {
        $table->id();
        $table->string('email')->unique();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    expect($this->schema->hasTable('users'))->toBeTrue();
    expect($this->schema->hasColumn('users', 'email'))->toBeTrue();
    expect($this->schema->hasColumn('users', 'created_at'))->toBeTrue();

    $this->schema->dropIfExists('users');

    expect($this->schema->hasTable('users'))->toBeFalse();
});

it('creates a table with foreign keys', function () {
    $this->schema->create('posts', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('title');
        $table->foreign('user_id')->references('id')->on('users');
    });

    expect($this->schema->hasTable('posts'))->toBeTrue();
});

it('compiles mysql create sql', function () {
    $this->config->set('database.default', 'mysql');
    $connection = $this->app->container->make(DatabaseManager::class)->connection();
    $grammar = $connection->getGrammar();

    $blueprint = new \TondbadSwoole\Database\Schema\Blueprint('users');
    $blueprint->build(function ($table): void {
        $table->id();
        $table->string('email')->unique();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    $sql = $grammar->compileCreate($blueprint)[0];

    expect($sql)->toContain('create table `users`');
    expect($sql)->toContain('`id` bigint unsigned not null auto_increment primary key');
    expect($sql)->toContain('`email` varchar(255) not null');
    expect($sql)->toContain('constraint `users_email_unique` unique (`email`)');
});
