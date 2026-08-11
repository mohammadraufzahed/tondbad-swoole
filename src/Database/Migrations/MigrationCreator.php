<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Migrations;

use InvalidArgumentException;
use RuntimeException;

class MigrationCreator
{
    public function create(string $name, string $path, ?string $table = null, bool $create = false): string
    {
        $this->ensureDirectory($path);

        $fileName = $this->getDatePrefix() . '_' . $name . '.php';
        $className = $this->getClassName($name);
        $filePath = $path . '/' . $fileName;

        if (file_exists($filePath)) {
            throw new RuntimeException("Migration {$fileName} already exists.");
        }

        $stub = $this->getStub($table, $create);
        $stub = str_replace(['{ClassName}', '{TableName}'], [$className, $table ?? ''], $stub);

        file_put_contents($filePath, $stub);

        return $filePath;
    }

    public function getClassName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]+/', '_', $name) ?? $name;
        $name = trim($name, '_');
        $segments = explode('_', $name);

        return implode('', array_map('ucfirst', $segments));
    }

    protected function getDatePrefix(): string
    {
        return date('Y_m_d_His');
    }

    protected function getStub(?string $table, bool $create): string
    {
        if ($table !== null && $create) {
            return <<<'STUB'
<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class {ClassName} extends Migration
{
    public function up(): void
    {
        schema()->create('{TableName}', function ($table): void {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('{TableName}');
    }
}

STUB;
        }

        if ($table !== null) {
            return <<<'STUB'
<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class {ClassName} extends Migration
{
    public function up(): void
    {
        schema()->table('{TableName}', function ($table): void {
            //
        });
    }

    public function down(): void
    {
        schema()->table('{TableName}', function ($table): void {
            //
        });
    }
}

STUB;
        }

        return <<<'STUB'
<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class {ClassName} extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
}

STUB;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}
