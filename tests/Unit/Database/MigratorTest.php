<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\Migrations\MigrationCreator;
use TondbadSwoole\Database\Migrations\Migrator;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_migrator_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $config = $this->app->container->make(Config::class);
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite.database', ':memory:');

    $this->migrator = $this->app->container->make(Migrator::class);
});

afterEach(function () {
    $this->migrator = null;
    $this->app = null;
});

it('creates the migrations table and runs a migration', function () {
    $creator = new MigrationCreator();
    $creator->create('create_users_table_first', "{$this->tmpDir}/database/migrations", 'users', true);

    $migrations = $this->migrator->run();

    expect($migrations)->toHaveCount(1);
    expect($this->migrator->getRepository()->getRan())->toHaveCount(1);

    $schema = $this->app->container->make(\TondbadSwoole\Database\DatabaseManager::class)
        ->connection()
        ->getSchemaBuilder();

    expect($schema->hasTable('users'))->toBeTrue();
    expect($schema->hasTable('migrations'))->toBeTrue();
});

it('rolls back the last batch of migrations', function () {
    $creator = new MigrationCreator();
    $creator->create('create_articles_table', "{$this->tmpDir}/database/migrations", 'articles', true);

    $this->migrator->run();
    $rolled = $this->migrator->rollback();

    expect($rolled)->toHaveCount(1);

    $schema = $this->app->container->make(\TondbadSwoole\Database\DatabaseManager::class)
        ->connection()
        ->getSchemaBuilder();

    expect($schema->hasTable('articles'))->toBeFalse();
});
