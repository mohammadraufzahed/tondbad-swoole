<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use TondbadSwoole\Database\Attributes\Column as ColumnAttribute;
use TondbadSwoole\Database\Attributes\GeneratedValue;
use TondbadSwoole\Database\Attributes\Id;
use TondbadSwoole\Database\Attributes\Table;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Database\Schema\Builder as SchemaBuilder;
use TondbadSwoole\Database\Schema\Column;

class SchemaTool
{
    public function __construct(private readonly SchemaBuilder $schemaBuilder)
    {
    }

    public function getSchemaBuilder(): SchemaBuilder
    {
        return $this->schemaBuilder;
    }

    /**
     * @param array<class-string<Model>> $classes
     */
    public function createSchema(array $classes): void
    {
        foreach ($this->getCreateSchemaSql($classes) as $statement) {
            $this->schemaBuilder->getConnection()->statement($statement);
        }
    }

    /**
     * @param array<class-string<Model>> $classes
     */
    public function dropSchema(array $classes): void
    {
        foreach ($classes as $class) {
            $this->assertModel($class);

            $this->schemaBuilder->dropIfExists($this->getTableName($class));
        }
    }

    /**
     * @param array<class-string<Model>> $classes
     * @return list<string>
     */
    public function getCreateSchemaSql(array $classes): array
    {
        $sql = [];
        $grammar = $this->schemaBuilder->getConnection()->getGrammar();

        foreach ($classes as $class) {
            $this->assertModel($class);

            $blueprint = new Blueprint($this->getTableName($class));
            $this->buildBlueprint($blueprint, $class);

            foreach ($grammar->compileCreate($blueprint) as $statement) {
                $sql[] = $statement;
            }
        }

        return $sql;
    }

    /**
     * @param array<class-string<Model>> $classes
     * @return list<string>
     */
    public function getUpdateSchemaSql(array $classes): array
    {
        $sql = [];
        $grammar = $this->schemaBuilder->getConnection()->getGrammar();

        foreach ($classes as $class) {
            $this->assertModel($class);

            $table = $this->getTableName($class);

            if (!$this->schemaBuilder->hasTable($table)) {
                $blueprint = new Blueprint($table);
                $this->buildBlueprint($blueprint, $class);

                foreach ($grammar->compileCreate($blueprint) as $statement) {
                    $sql[] = $statement;
                }

                continue;
            }

            foreach ($this->getMissingColumns($class, $table) as $column) {
                $sql[] = $grammar->compileAddColumn($table, $column);
            }
        }

        return $sql;
    }

    /**
     * @param array<class-string<Model>> $classes
     */
    public function updateSchema(array $classes): void
    {
        foreach ($this->getUpdateSchemaSql($classes) as $statement) {
            $this->schemaBuilder->getConnection()->statement($statement);
        }
    }

    /**
     * @param class-string<Model> $class
     */
    public function getTableName(string $class): string
    {
        $reflection = new ReflectionClass($class);
        $tableAttributes = $reflection->getAttributes(Table::class);

        if ($tableAttributes !== []) {
            return $tableAttributes[0]->newInstance()->name;
        }

        if (is_subclass_of($class, Model::class)) {
            return (new $class())->getTable();
        }

        throw new RuntimeException("Cannot resolve table name for class [{$class}].");
    }

    /**
     * @param class-string<Model> $class
     */
    private function buildBlueprint(Blueprint $blueprint, string $class): void
    {
        foreach ($this->getEntityProperties($class) as $property) {
            $this->addColumn($blueprint, $property);
        }
    }

    /**
     * @param class-string<Model> $class
     * @return list<ReflectionProperty>
     */
    private function getEntityProperties(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if ($property->getDeclaringClass()->getName() === Model::class) {
                continue;
            }

            if ($property->getAttributes(ColumnAttribute::class) === [] && $property->getAttributes(Id::class) === []) {
                continue;
            }

            $properties[] = $property;
        }

        return $properties;
    }

    private function addColumn(Blueprint $blueprint, ReflectionProperty $property): void
    {
        $idAttributes = $property->getAttributes(Id::class);
        $columnAttributes = $property->getAttributes(ColumnAttribute::class);

        if ($idAttributes !== [] && $columnAttributes === []) {
            $blueprint->bigIncrements($property->getName());

            return;
        }

        if ($columnAttributes === []) {
            return;
        }

        $column = $columnAttributes[0]->newInstance();
        $name = $column->name ?? $property->getName();
        $isId = $idAttributes !== [];
        $autoIncrement = $column->autoIncrement || $property->getAttributes(GeneratedValue::class) !== [];

        $parameters = [
            'nullable' => $column->nullable,
            'unsigned' => $column->unsigned,
            'autoIncrement' => $autoIncrement,
            'primary' => $isId || $column->primary,
            'default' => $column->default,
            'comment' => $column->comment,
            'length' => $column->length,
            'total' => $column->total ?? $column->precision,
            'places' => $column->places ?? $column->scale,
            'allowed' => $column->allowed,
        ];

        $blueprint->addColumn($column->type, $name, $parameters);

        if ($column->unique) {
            $blueprint->unique($name);
        }

        if ($column->index) {
            $blueprint->index($name);
        }
    }

    /**
     * @param class-string<Model> $class
     * @return list<Column>
     */
    private function getMissingColumns(string $class, string $table): array
    {
        $blueprint = new Blueprint($table);
        $this->buildBlueprint($blueprint, $class);

        $missing = [];

        foreach ($blueprint->columns as $column) {
            if (!$this->schemaBuilder->hasColumn($table, $column->name)) {
                $missing[] = $column;
            }
        }

        return $missing;
    }

    /**
     * @param class-string<Model> $class
     */
    private function assertModel(string $class): void
    {
        if (!is_subclass_of($class, Model::class) && $class !== Model::class) {
            throw new InvalidArgumentException("Class [{$class}] must extend " . Model::class . '.');
        }
    }
}
