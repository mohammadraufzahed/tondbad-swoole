<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

final class LiveRender
{
    /**
     * Wrap a fresh component render with the root live segment markers and state token.
     */
    public static function wrap(string $html, string $token): string
    {
        $html = self::injectState($html, $token);

        return '<!--t:d:0-->' . $html . '<!--/t:d:0-->';
    }

    /**
     * Replace the <data-t-state> placeholder with the serialized state token input.
     */
    public static function injectState(string $html, string $token): string
    {
        $search = '<data-t-state></data-t-state>';

        if (str_contains($html, $search)) {
            $input = '<input type="hidden" name="t:state" value="' . e($token) . '">';

            return str_replace($search, $input, $html);
        }

        $input = '<input type="hidden" name="t:state" value="' . e($token) . '">';

        if (preg_match('/(<[a-zA-Z][^>]*>)/', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $tag = $matches[1][0];
            $position = $matches[1][1] + strlen($tag);

            return substr($html, 0, $position) . $input . substr($html, $position);
        }

        return $html . $input;
    }

    /**
     * Extract a segment by id from a rendered string with markers.
     */
    public static function segment(string $html, int $id): string
    {
        $start = '<!--t:d:' . $id . '-->';
        $end = '<!--/t:d:' . $id . '-->';

        $startPos = strpos($html, $start);

        if ($startPos === false) {
            return '';
        }

        $endPos = strpos($html, $end, $startPos + strlen($start));

        if ($endPos === false) {
            return '';
        }

        return substr($html, $startPos + strlen($start), $endPos - $startPos - strlen($start));
    }
}
