<?php

declare(strict_types=1);

it('returns default when key is missing', function () {
    expect($this->env->get('missing.key', 'default'))->toBe('default');
});

it('reads values from an env file', function () {
    $tmpDir = $this->tempDir('tondbad_env_test');
    file_put_contents("{$tmpDir}/.env", "APP_NAME=TondbadTest\n");

    $this->env->load([$tmpDir]);

    expect($this->env->get('app.name'))->toBe('TondbadTest');
});

it('parses booleans, numbers, and null', function () {
    $tmpDir = $this->tempDir('tondbad_env_test');
    file_put_contents(
        "{$tmpDir}/.env",
        "APP_DEBUG=true\nAPP_CACHE=false\nAPP_PORT=9501\nAPP_RATIO=3.14\nAPP_EMPTY=null\n"
    );

    $this->env->load([$tmpDir]);

    expect($this->env->get('app.debug'))->toBeTrue();
    expect($this->env->get('app.cache'))->toBeFalse();
    expect($this->env->get('app.port'))->toBe(9501);
    expect($this->env->get('app.ratio'))->toBe(3.14);
    expect($this->env->get('app.empty'))->toBeNull();
});

it('recognizes falsey and empty values', function () {
    $tmpDir = $this->tempDir('tondbad_env_test');
    file_put_contents("{$tmpDir}/.env", "APP_DEBUG=false\nAPP_NAME=\n");

    $this->env->load([$tmpDir]);

    expect($this->env->has('app.debug'))->toBeTrue();
    expect($this->env->get('app.debug'))->toBeFalse();
    expect($this->env->has('app.name'))->toBeTrue();
    expect($this->env->get('app.name'))->toBe('');
});

it('keeps project env over framework env', function () {
    $projectDir = $this->tempDir('tondbad_env_test');
    $frameworkDir = $this->tempDir('tondbad_env_test');

    file_put_contents("{$projectDir}/.env", "APP_NAME=project\n");
    file_put_contents("{$frameworkDir}/.env", "APP_NAME=framework\n");

    $this->env->load([$projectDir, $frameworkDir]);

    expect($this->env->get('app.name'))->toBe('project');
});

it('does not call putenv or getenv for dotenv values', function () {
    $tmpDir = $this->tempDir('tondbad_env_test');
    file_put_contents("{$tmpDir}/.env", "APP_NAME=FromFile\n");

    $this->env->load([$tmpDir]);

    expect($this->env->get('app.name'))->toBe('FromFile');
    expect(getenv('APP_NAME'))->toBeFalse();
});
