<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Exceptions\ConfigurationException;
use TondbadSwoole\Validation\Schema;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_config_validation_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
});

it('validates config values with a schema', function () {
    /** @var Config $config */
    $config = $this->app->container->make(Config::class);

    $value = $config->validate('app.type', Schema::enum('http', 'grpc'));

    expect($value)->toBe('http');
});

it('throws when config value fails schema validation', function () {
    /** @var Config $config */
    $config = $this->app->container->make(Config::class);

    $config->validate('app.type', Schema::enum('grpc'));
})->throws(ConfigurationException::class);

it('returns typed env values', function () {
    putenv('TEST_PORT=8080');
    putenv('TEST_DEBUG=true');

    $env = $this->app->env;

    expect($env->getInt('test.port'))->toBe(8080)
        ->and($env->getBool('test.debug'))->toBeTrue()
        ->and($env->getString('test.missing', 'default'))->toBe('default');

    putenv('TEST_PORT');
    putenv('TEST_DEBUG');
});
