<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Attributes\OnCreate;
use TondbadSwoole\Database\Attributes\OnDelete;
use TondbadSwoole\Database\Attributes\OnFlush;
use TondbadSwoole\Database\Attributes\OnLoad;
use TondbadSwoole\Database\Attributes\OnUpdate;

class EventedUser extends User
{
    public array $lifecycleEvents = [];

    #[OnCreate]
    public function onCreate(): void
    {
        $this->lifecycleEvents[] = 'onCreate';
    }

    #[OnUpdate]
    public function onUpdate(): void
    {
        $this->lifecycleEvents[] = 'onUpdate';
    }

    #[OnDelete]
    public function onDelete(): void
    {
        $this->lifecycleEvents[] = 'onDelete';
    }

    #[OnLoad]
    public function onLoad(): void
    {
        $this->lifecycleEvents[] = 'onLoad';
    }

    #[OnFlush]
    public function onFlush(): void
    {
        $this->lifecycleEvents[] = 'onFlush';
    }
}
