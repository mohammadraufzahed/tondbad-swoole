<?php

declare(strict_types=1);

namespace TondbadSwoole\View;

final class AnonymousComponent extends Component
{
    private readonly ?ViewManager $manager;

    public function __construct(private readonly string $view, array $data = [])
    {
        $this->manager = $data['__manager'] ?? null;
        unset($data['__manager']);

        parent::__construct($data);
    }

    public function render(): View
    {
        $manager = $this->manager ?? app()?->container->make(ViewManager::class);

        return new View($manager, $this->view, $this->attributes->toArray());
    }
}
