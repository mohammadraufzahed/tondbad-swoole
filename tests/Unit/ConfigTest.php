<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit;

class ConfigTest extends TestCase
{
    public function test_get_uses_env_value_over_config_file(): void
    {
        $tmpDir = $this->createTempDir();

        file_put_contents("{$tmpDir}/.env", "APP_DEBUG=false\n");
        file_put_contents(
            "{$tmpDir}/app.php",
            "<?php\nreturn ['debug' => \$env->get('app.debug', true)];\n"
        );

        $this->env->load([$tmpDir]);
        $this->setConfigSearchPaths([$tmpDir]);

        $this->assertFalse($this->config->get('app.debug', true));
    }

    public function test_get_preserves_falsey_env_values(): void
    {
        $tmpDir = $this->createTempDir();

        file_put_contents("{$tmpDir}/.env", "APP_VALUE=0\n");
        file_put_contents(
            "{$tmpDir}/app.php",
            "<?php\nreturn ['value' => \$env->get('app.value', 'default')];\n"
        );

        $this->env->load([$tmpDir]);
        $this->setConfigSearchPaths([$tmpDir]);

        $this->assertSame(0, $this->config->get('app.value', 'default'));
    }

    public function test_get_preserves_empty_env_values(): void
    {
        $tmpDir = $this->createTempDir();

        file_put_contents("{$tmpDir}/.env", "APP_VALUE=\n");
        file_put_contents(
            "{$tmpDir}/app.php",
            "<?php\nreturn ['value' => \$env->get('app.value', 'default')];\n"
        );

        $this->env->load([$tmpDir]);
        $this->setConfigSearchPaths([$tmpDir]);

        $this->assertSame('', $this->config->get('app.value', 'default'));
    }

    public function test_get_falls_back_to_config_default(): void
    {
        $tmpDir = $this->createTempDir();

        file_put_contents(
            "{$tmpDir}/app.php",
            "<?php\nreturn ['debug' => true];\n"
        );

        $this->setConfigSearchPaths([$tmpDir]);

        $this->assertTrue($this->config->get('app.debug', false));
    }

    public function test_get_uses_default_when_key_missing(): void
    {
        $tmpDir = $this->createTempDir();

        file_put_contents("{$tmpDir}/.env", "\n");
        file_put_contents("{$tmpDir}/app.php", "<?php\nreturn [];\n");

        $this->env->load([$tmpDir]);
        $this->setConfigSearchPaths([$tmpDir]);

        $this->assertSame('fallback', $this->config->get('app.missing', 'fallback'));
    }

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/tondbad_config_test_' . uniqid();
        mkdir($dir, 0777, true);

        return $dir;
    }
}
