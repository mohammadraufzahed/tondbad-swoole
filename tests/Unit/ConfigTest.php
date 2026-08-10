<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Env;

class ConfigTest extends TestCase
{
    public function test_get_uses_env_value_over_config_file(): void
    {
        $tmpDir = $this->createTempDir();

        file_put_contents("{$tmpDir}/.env", "APP_DEBUG=false\n");
        file_put_contents(
            "{$tmpDir}/app.php",
            "<?php\nuse TondbadSwoole\\Core\\Env;\nreturn ['debug' => Env::get('app.debug', true)];\n"
        );

        Env::load([$tmpDir]);
        $this->setConfigSearchPaths([$tmpDir]);

        $this->assertFalse(Config::get('app.debug', true));
    }

    public function test_get_preserves_falsey_env_values(): void
    {
        $tmpDir = $this->createTempDir();

        file_put_contents("{$tmpDir}/.env", "APP_VALUE=0\n");
        file_put_contents(
            "{$tmpDir}/app.php",
            "<?php\nuse TondbadSwoole\\Core\\Env;\nreturn ['value' => Env::get('app.value', 'default')];\n"
        );

        Env::load([$tmpDir]);
        $this->setConfigSearchPaths([$tmpDir]);

        $this->assertSame(0, Config::get('app.value', 'default'));
    }

    public function test_get_preserves_empty_env_values(): void
    {
        $tmpDir = $this->createTempDir();

        file_put_contents("{$tmpDir}/.env", "APP_VALUE=\n");
        file_put_contents(
            "{$tmpDir}/app.php",
            "<?php\nuse TondbadSwoole\\Core\\Env;\nreturn ['value' => Env::get('app.value', 'default')];\n"
        );

        Env::load([$tmpDir]);
        $this->setConfigSearchPaths([$tmpDir]);

        $this->assertSame('', Config::get('app.value', 'default'));
    }

    public function test_get_falls_back_to_config_default(): void
    {
        $tmpDir = $this->createTempDir();

        file_put_contents(
            "{$tmpDir}/app.php",
            "<?php\nreturn ['debug' => true];\n"
        );

        $this->setConfigSearchPaths([$tmpDir]);

        $this->assertTrue(Config::get('app.debug', false));
    }

    public function test_get_uses_default_when_key_missing(): void
    {
        $tmpDir = $this->createTempDir();

        file_put_contents("{$tmpDir}/.env", "\n");
        file_put_contents("{$tmpDir}/app.php", "<?php\nreturn [];\n");

        Env::load([$tmpDir]);
        $this->setConfigSearchPaths([$tmpDir]);

        $this->assertSame('fallback', Config::get('app.missing', 'fallback'));
    }

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/tondbad_config_test_' . uniqid();
        mkdir($dir, 0777, true);

        return $dir;
    }
}
