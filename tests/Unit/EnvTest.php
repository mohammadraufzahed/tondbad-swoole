<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit;

use TondbadSwoole\Core\Env;

class EnvTest extends TestCase
{
    public function test_get_returns_default_when_key_missing(): void
    {
        $this->assertSame('default', Env::get('missing.key', 'default'));
    }

    public function test_load_reads_values_from_env_file(): void
    {
        $tmpDir = $this->createTempDir();
        file_put_contents("{$tmpDir}/.env", "APP_NAME=TondbadTest\n");

        Env::load([$tmpDir]);

        $this->assertSame('TondbadTest', Env::get('app.name'));
    }

    public function test_get_parses_booleans_numbers_and_null(): void
    {
        $tmpDir = $this->createTempDir();
        file_put_contents(
            "{$tmpDir}/.env",
            "APP_DEBUG=true\nAPP_CACHE=false\nAPP_PORT=9501\nAPP_RATIO=3.14\nAPP_EMPTY=null\n"
        );

        Env::load([$tmpDir]);

        $this->assertTrue(Env::get('app.debug'));
        $this->assertFalse(Env::get('app.cache'));
        $this->assertSame(9501, Env::get('app.port'));
        $this->assertSame(3.14, Env::get('app.ratio'));
        $this->assertNull(Env::get('app.empty'));
    }

    public function test_has_recognizes_falsey_and_empty_values(): void
    {
        $tmpDir = $this->createTempDir();
        file_put_contents("{$tmpDir}/.env", "APP_DEBUG=false\nAPP_NAME=\n");

        Env::load([$tmpDir]);

        $this->assertTrue(Env::has('app.debug'));
        $this->assertFalse(Env::get('app.debug'));
        $this->assertTrue(Env::has('app.name'));
        $this->assertSame('', Env::get('app.name'));
    }

    public function test_load_keeps_project_env_over_framework_env(): void
    {
        $projectDir = $this->createTempDir();
        $frameworkDir = $this->createTempDir();

        file_put_contents("{$projectDir}/.env", "APP_NAME=project\n");
        file_put_contents("{$frameworkDir}/.env", "APP_NAME=framework\n");

        Env::load([$projectDir, $frameworkDir]);

        $this->assertSame('project', Env::get('app.name'));
    }

    public function test_get_does_not_call_putenv_or_getenv_for_dotenv_values(): void
    {
        $tmpDir = $this->createTempDir();
        file_put_contents("{$tmpDir}/.env", "APP_NAME=FromFile\n");

        Env::load([$tmpDir]);

        $this->assertSame('FromFile', Env::get('app.name'));
        $this->assertFalse(getenv('APP_NAME'));
    }

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/tondbad_env_test_' . uniqid();
        mkdir($dir, 0777, true);

        return $dir;
    }
}
