<?php

declare(strict_types=1);

namespace TondbadSwoole\View;

final class AnonymousComponent extends Component
{
    public function __construct(private readonly string $view, array $data = [])
    {
        parent::__construct($data);
        $this->view = $view;
    }

    public function render(): View
    {
        /** @var ViewManager $manager */
        $manager = $this->attributes->get('__manager');

        return new View($manager, $this->view, $this->attributes->toArray());
    }
}
