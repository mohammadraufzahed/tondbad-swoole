<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Migrations;

use InvalidArgumentException;
use RuntimeException;

class MigrationCreator
{
    public function __construct(
        protected string $stubPath = __DIR__ . '/../../../stubs',
    ) {
    }

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
        $stubFile = match (true) {
            $table !== null && $create => 'migration.create.stub',
            $table !== null => 'migration.table.stub',
            default => 'migration.blank.stub',
        };

        $path = $this->stubPath . '/' . $stubFile;
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Migration stub not found: {$path}");
        }

        return $content;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}
