<?php

declare(strict_types=1);

namespace TondbadSwoole\View;

abstract class AbstractCompiledView
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data, ViewContext $ctx): string
    {
        ob_start();
        $this->renderInternal($data, $ctx);

        return ob_get_clean();
    }

    /**
     * @param array<string, mixed> $data
     */
    abstract protected function renderInternal(array $data, ViewContext $ctx): void;
}
