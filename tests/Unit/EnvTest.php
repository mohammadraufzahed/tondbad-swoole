<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit;

class EnvTest extends TestCase
{
    public function test_get_returns_default_when_key_missing(): void
    {
        $this->assertSame('default', $this->env->get('missing.key', 'default'));
    }

    public function test_load_reads_values_from_env_file(): void
    {
        $tmpDir = $this->createTempDir();
        file_put_contents("{$tmpDir}/.env", "APP_NAME=TondbadTest\n");

        $this->env->load([$tmpDir]);

        $this->assertSame('TondbadTest', $this->env->get('app.name'));
    }

    public function test_get_parses_booleans_numbers_and_null(): void
    {
        $tmpDir = $this->createTempDir();
        file_put_contents(
            "{$tmpDir}/.env",
            "APP_DEBUG=true\nAPP_CACHE=false\nAPP_PORT=9501\nAPP_RATIO=3.14\nAPP_EMPTY=null\n"
        );

        $this->env->load([$tmpDir]);

        $this->assertTrue($this->env->get('app.debug'));
        $this->assertFalse($this->env->get('app.cache'));
        $this->assertSame(9501, $this->env->get('app.port'));
        $this->assertSame(3.14, $this->env->get('app.ratio'));
        $this->assertNull($this->env->get('app.empty'));
    }

    public function test_has_recognizes_falsey_and_empty_values(): void
    {
        $tmpDir = $this->createTempDir();
        file_put_contents("{$tmpDir}/.env", "APP_DEBUG=false\nAPP_NAME=\n");

        $this->env->load([$tmpDir]);

        $this->assertTrue($this->env->has('app.debug'));
        $this->assertFalse($this->env->get('app.debug'));
        $this->assertTrue($this->env->has('app.name'));
        $this->assertSame('', $this->env->get('app.name'));
    }

    public function test_load_keeps_project_env_over_framework_env(): void
    {
        $projectDir = $this->createTempDir();
        $frameworkDir = $this->createTempDir();

        file_put_contents("{$projectDir}/.env", "APP_NAME=project\n");
        file_put_contents("{$frameworkDir}/.env", "APP_NAME=framework\n");

        $this->env->load([$projectDir, $frameworkDir]);

        $this->assertSame('project', $this->env->get('app.name'));
    }

    public function test_get_does_not_call_putenv_or_getenv_for_dotenv_values(): void
    {
        $tmpDir = $this->createTempDir();
        file_put_contents("{$tmpDir}/.env", "APP_NAME=FromFile\n");

        $this->env->load([$tmpDir]);

        $this->assertSame('FromFile', $this->env->get('app.name'));
        $this->assertFalse(getenv('APP_NAME'));
    }

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/tondbad_env_test_' . uniqid();
        mkdir($dir, 0777, true);

        return $dir;
    }
}
