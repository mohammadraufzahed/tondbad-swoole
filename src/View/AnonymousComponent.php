<?php

declare(strict_types=1);

namespace TondbadSwoole\View;

final class AnonymousComponent extends Component
{
    public function __construct(private readonly string $view, array $data = [])
    {
        parent::__construct($data);
    }

    public function render(): View
    {
        /** @var ViewManager|null $manager */
        $manager = $this->attributes->get('__manager') ?? app()?->container->make(ViewManager::class);

        return new View($manager, $this->view, $this->attributes->toArray());
    }
}
