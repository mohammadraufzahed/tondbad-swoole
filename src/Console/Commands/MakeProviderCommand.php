<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakeProviderCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:provider';
    }

    public function getDescription(): string
    {
        return 'Create a new service provider class.';
    }

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Providers;

use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class {Name}ServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Register services here.
    }
}

STUB;
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Providers/' . $name . 'ServiceProvider.php';
    }
}
