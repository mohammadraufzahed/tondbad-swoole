<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

final class LivePatcher
{
    /**
     * @return list<array{type: string, id: int, html: string}>
     */
    public static function diff(string $oldHtml, string $newHtml): array
    {
        $oldSegment = LiveRender::segment($oldHtml, 0);
        $newSegment = LiveRender::segment($newHtml, 0);

        if ($oldSegment === $newSegment) {
            return [];
        }

        return [
            [
                'type' => 'replace',
                'id' => 0,
                'html' => $newHtml,
            ],
        ];
    }
}
