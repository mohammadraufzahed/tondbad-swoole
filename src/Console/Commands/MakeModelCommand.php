<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakeModelCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:model';
    }

    public function getDescription(): string
    {
        return 'Create a new model class.';
    }

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Models;

use TondbadSwoole\Database\Model;

class {Name} extends Model
{
    protected ?string $table = null;

    protected array $fillable = [];

    protected array $casts = [];
}

STUB;
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Models/' . $name . '.php';
    }
}
