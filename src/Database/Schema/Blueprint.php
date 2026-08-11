<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Schema;

use Closure;
use RuntimeException;

class Blueprint
{
    public array $columns = [];

    public array $indexes = [];

    public array $foreignKeys = [];

    public ?Column $currentColumn = null;

    public ?ForeignKey $currentForeignKey = null;

    public bool $temporary = false;

    public ?string $engine = null;

    public ?string $charset = null;

    public ?string $collation = null;

    public ?string $comment = null;

    public ?string $renameTo = null;

    public function __construct(public string $table)
    {
    }

    public function build(Closure $callback): void
    {
        $callback($this);
    }

    public function temporary(): self
    {
        $this->temporary = true;

        return $this;
    }

    public function engine(string $engine): self
    {
        $this->engine = $engine;

        return $this;
    }

    public function charset(string $charset): self
    {
        $this->charset = $charset;

        return $this;
    }

    public function collation(string $collation): self
    {
        $this->collation = $collation;

        return $this;
    }

    public function comment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function addColumn(string $type, string $name, array $parameters = []): self
    {
        $column = new Column($type, $name);

        foreach ($parameters as $key => $value) {
            if (property_exists($column, $key)) {
                $column->$key = $value;
            }
        }

        if ($column->autoIncrement) {
            $column->primary = true;
        }

        $this->columns[] = $column;
        $this->currentColumn = $column;
        $this->currentForeignKey = null;

        return $this;
    }

    public function id(?string $column = null): self
    {
        return $this->bigIncrements($column ?? 'id');
    }

    public function increments(string $column): self
    {
        return $this->integer($column, true, true);
    }

    public function bigIncrements(string $column): self
    {
        return $this->bigInteger($column, true, true);
    }

    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function string(string $column, int $length = 255): self
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    public function char(string $column, int $length): self
    {
        return $this->addColumn('char', $column, compact('length'));
    }

    public function text(string $column): self
    {
        return $this->addColumn('text', $column);
    }

    public function mediumText(string $column): self
    {
        return $this->addColumn('mediumText', $column);
    }

    public function longText(string $column): self
    {
        return $this->addColumn('longText', $column);
    }

    public function boolean(string $column): self
    {
        return $this->addColumn('boolean', $column);
    }

    public function json(string $column): self
    {
        return $this->addColumn('json', $column);
    }

    public function jsonb(string $column): self
    {
        return $this->addColumn('jsonb', $column);
    }

    public function datetime(string $column, int $precision = 0): self
    {
        return $this->addColumn('datetime', $column, compact('precision'));
    }

    public function date(string $column): self
    {
        return $this->addColumn('date', $column);
    }

    public function time(string $column, int $precision = 0): self
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    public function timestamp(string $column, int $precision = 0): self
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    public function softDeletes(string $column = 'deleted_at', int $precision = 0): self
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    public function enum(string $column, array $allowed): self
    {
        return $this->addColumn('enum', $column, ['allowed' => $allowed]);
    }

    public function decimal(string $column, int $total = 8, int $places = 2): self
    {
        return $this->addColumn('decimal', $column, compact('total', 'places'));
    }

    public function float(string $column, int $precision = 0): self
    {
        return $this->addColumn('float', $column, compact('precision'));
    }

    public function double(string $column, int $total = 0, int $places = 0): self
    {
        return $this->addColumn('double', $column, compact('total', 'places'));
    }

    public function binary(string $column): self
    {
        return $this->addColumn('binary', $column);
    }

    public function uuid(string $column = 'uuid'): self
    {
        return $this->char($column, 36);
    }

    public function rememberToken(): self
    {
        return $this->string('remember_token', 100)->nullable();
    }

    public function ipAddress(string $column = 'ip_address'): self
    {
        return $this->string($column, 45);
    }

    public function macAddress(string $column = 'mac_address'): self
    {
        return $this->string($column, 17);
    }

    public function morphs(string $name, ?string $indexName = null): void
    {
        $this->unsignedBigInteger("{$name}_id");
        $this->string("{$name}_type");
        $this->index(["{$name}_id", "{$name}_type"], $indexName);
    }

    public function unsignedBigInteger(string $column, bool $autoIncrement = false): self
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    public function unsignedInteger(string $column, bool $autoIncrement = false): self
    {
        return $this->integer($column, $autoIncrement, true);
    }

    public function nullable(bool $value = true): self
    {
        $this->ensureCurrentColumn();
        $this->currentColumn->nullable = $value;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->ensureCurrentColumn();
        $this->currentColumn->default = $value;

        return $this;
    }

    public function useCurrent(): self
    {
        $this->ensureCurrentColumn();
        $this->currentColumn->useCurrent = true;

        return $this;
    }

    public function unsigned(): self
    {
        $this->ensureCurrentColumn();
        $this->currentColumn->unsigned = true;

        return $this;
    }

    public function autoIncrement(): self
    {
        $this->ensureCurrentColumn();
        $this->currentColumn->autoIncrement = true;

        return $this;
    }

    public function first(): self
    {
        $this->ensureCurrentColumn();
        $this->currentColumn->first = true;
        $this->currentColumn->after = null;

        return $this;
    }

    public function after(string $column): self
    {
        $this->ensureCurrentColumn();
        $this->currentColumn->after = $column;
        $this->currentColumn->first = false;

        return $this;
    }

    public function index(array|string|null $columns = null, ?string $name = null, ?string $algorithm = null): self
    {
        $this->addIndex('index', $columns, $name, $algorithm);

        return $this;
    }

    public function unique(array|string|null $columns = null, ?string $name = null): self
    {
        $this->addIndex('unique', $columns, $name);

        return $this;
    }

    public function primary(array|string|null $columns = null, ?string $name = null): self
    {
        $this->addIndex('primary', $columns, $name);

        return $this;
    }

    public function foreign(array|string $columns, ?string $name = null): ForeignKey
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name ??= $this->defaultForeignKeyName($columns);

        $foreignKey = new ForeignKey($name, $columns);
        $this->foreignKeys[] = $foreignKey;
        $this->currentForeignKey = $foreignKey;
        $this->currentColumn = null;

        return $foreignKey;
    }

    public function dropColumn(array|string $columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        foreach ($columns as $column) {
            $this->columns[] = new Column('dropColumn', $column);
        }

        return $this;
    }

    public function renameColumn(string $from, string $to): self
    {
        $column = new Column('renameColumn', $to);
        $column->renameFrom = $from;
        $this->columns[] = $column;

        return $this;
    }

    public function dropIndex(array|string $index): self
    {
        $this->indexes[] = new Index($this->defaultIndexName('index', is_array($index) ? $index : [$index]), (array) $index, 'dropIndex');

        return $this;
    }

    public function dropUnique(string $index): self
    {
        $this->indexes[] = new Index($index, [], 'dropUnique');

        return $this;
    }

    public function dropPrimary(string $index = ''): self
    {
        $this->indexes[] = new Index($index, [], 'dropPrimary');

        return $this;
    }

    public function dropForeign(string $index): self
    {
        $this->foreignKeys[] = new ForeignKey($index, [], 'dropForeign');

        return $this;
    }

    public function renameTo(string $name): self
    {
        $this->renameTo = $name;

        return $this;
    }

    private function ensureCurrentColumn(): void
    {
        if ($this->currentColumn === null) {
            throw new RuntimeException('No column has been added yet. Call a column method first.');
        }
    }

    private function addIndex(string $type, array|string|null $columns, ?string $name, ?string $algorithm = null): void
    {
        if ($columns === null) {
            $this->ensureCurrentColumn();
            $columns = [$this->currentColumn->name];
        }

        $columns = is_array($columns) ? $columns : [$columns];
        $name ??= $this->defaultIndexName($type, $columns);

        $this->indexes[] = new Index($name, $columns, $type, $algorithm);
    }

    private function defaultIndexName(string $type, array $columns): string
    {
        $columns = implode('_', $columns);

        return strtolower($this->table . '_' . $columns . '_' . $type);
    }

    private function defaultForeignKeyName(array $columns): string
    {
        return strtolower($this->table . '_' . implode('_', $columns) . '_foreign');
    }
}
