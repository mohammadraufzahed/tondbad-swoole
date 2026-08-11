<?php

declare(strict_types=1);

it('uses env value over config file', function () {
    $tmpDir = $this->tempDir('tondbad_config_test');

    file_put_contents("{$tmpDir}/.env", "APP_DEBUG=false\n");
    file_put_contents(
        "{$tmpDir}/app.php",
        "<?php\nreturn ['debug' => \$env->get('app.debug', true)];\n"
    );

    $this->env->load([$tmpDir]);
    $this->setConfigSearchPaths([$tmpDir]);

    expect($this->config->get('app.debug', true))->toBeFalse();
});

it('preserves falsey env values', function () {
    $tmpDir = $this->tempDir('tondbad_config_test');

    file_put_contents("{$tmpDir}/.env", "APP_VALUE=0\n");
    file_put_contents(
        "{$tmpDir}/app.php",
        "<?php\nreturn ['value' => \$env->get('app.value', 'default')];\n"
    );

    $this->env->load([$tmpDir]);
    $this->setConfigSearchPaths([$tmpDir]);

    expect($this->config->get('app.value', 'default'))->toBe(0);
});

it('preserves empty env values', function () {
    $tmpDir = $this->tempDir('tondbad_config_test');

    file_put_contents("{$tmpDir}/.env", "APP_VALUE=\n");
    file_put_contents(
        "{$tmpDir}/app.php",
        "<?php\nreturn ['value' => \$env->get('app.value', 'default')];\n"
    );

    $this->env->load([$tmpDir]);
    $this->setConfigSearchPaths([$tmpDir]);

    expect($this->config->get('app.value', 'default'))->toBe('');
});

it('falls back to config default', function () {
    $tmpDir = $this->tempDir('tondbad_config_test');

    file_put_contents(
        "{$tmpDir}/app.php",
        "<?php\nreturn ['debug' => true];\n"
    );

    $this->setConfigSearchPaths([$tmpDir]);

    expect($this->config->get('app.debug', false))->toBeTrue();
});

it('uses default when key missing', function () {
    $tmpDir = $this->tempDir('tondbad_config_test');

    file_put_contents("{$tmpDir}/.env", "\n");
    file_put_contents("{$tmpDir}/app.php", "<?php\nreturn [];\n");

    $this->env->load([$tmpDir]);
    $this->setConfigSearchPaths([$tmpDir]);

    expect($this->config->get('app.missing', 'fallback'))->toBe('fallback');
});
