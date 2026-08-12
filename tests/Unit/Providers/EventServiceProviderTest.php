<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Events\Dispatcher;

it('discovers listeners from app/Listeners', function () {
    $tmpDir = $this->tempDir('tondbad_event_provider_test');
    mkdir("{$tmpDir}/config", 0777, true);
    mkdir("{$tmpDir}/app/Listeners", 0777, true);
    mkdir("{$tmpDir}/database/migrations", 0777, true);
    mkdir("{$tmpDir}/storage/logs", 0777, true);
    mkdir("{$tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");
    file_put_contents("{$tmpDir}/config/providers.php", "<?php\n" . 'return ' . var_export([
        TondbadSwoole\Providers\Default\EventServiceProvider::class,
        TondbadSwoole\Providers\Default\ConsoleServiceProvider::class,
    ], true) . ";\n");

    file_put_contents("{$tmpDir}/app/Listeners/LogUserCreated.php", <<<'PHP'
<?php
declare(strict_types=1);

namespace App\Listeners;

use TondbadSwoole\Events\Listener;

#[Listener(events: ['user.created'])]
class LogUserCreated
{
    public static array $log = [];

    public function handle($payload): void
    {
        self::$log[] = $payload;
    }
}
PHP);

    require "{$tmpDir}/app/Listeners/LogUserCreated.php";

    $app = AppFactory::create($tmpDir)->boot();
    $dispatcher = $app->container->make(Dispatcher::class);
    $dispatcher->dispatch('user.created', 'payload');

    expect(\App\Listeners\LogUserCreated::$log)->toContain('payload');
});
