<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Compilers;

final class Tokenizer
{
    private string $template;

    private int $length;

    private int $pos = 0;

    /** @var list<Token> */
    private array $tokens = [];

    /**
     * @return list<Token>
     */
    public function tokenize(string $template): array
    {
        $this->template = $template;
        $this->length = strlen($template);
        $this->pos = 0;
        $this->tokens = [];

        $this->scan();

        return $this->tokens;
    }

    private function scan(): void
    {
        while ($this->pos < $this->length) {
            $remaining = substr($this->template, $this->pos);

            if (str_starts_with($remaining, '{{--')) {
                $this->scanComment();

                continue;
            }

            if (str_starts_with($remaining, '{!!')) {
                $this->scanRawEcho();

                continue;
            }

            if (str_starts_with($remaining, '{{')) {
                $this->scanEcho();

                continue;
            }

            if (str_starts_with($remaining, '@{{')) {
                $this->tokens[] = new Token('text', '{{');
                $this->pos += 3;

                continue;
            }

            if ($this->template[$this->pos] === '@' && $this->isDirectiveStart($remaining)) {
                $this->scanDirective();

                continue;
            }

            if (str_starts_with($remaining, '<x-') || str_starts_with($remaining, '</x-')) {
                $this->scanComponentTag();

                continue;
            }

            $this->scanText();
        }
    }

    private function isDirectiveStart(string $remaining): bool
    {
        if ($remaining === '' || $remaining[0] !== '@') {
            return false;
        }

        $name = substr($remaining, 1);

        if (!preg_match('/^[A-Za-z_-]+/', $name, $matches)) {
            return false;
        }

        $after = substr($name, strlen($matches[0]));
        $next = $after[0] ?? '';

        if ($next === '' || $next === '(' || ctype_space($next)) {
            return true;
        }

        // Avoid treating e-mail addresses/mentions like foo@bar.com as directives.
        if ($next === '.' && isset($after[1]) && preg_match('/[A-Za-z_-]/', $after[1])) {
            return false;
        }

        return !preg_match('/[A-Za-z0-9_]/', $next);
    }

    private function scanComment(): void
    {
        $end = strpos($this->template, '--}}', $this->pos);

        if ($end === false) {
            $this->tokens[] = new Token('text', substr($this->template, $this->pos));
            $this->pos = $this->length;

            return;
        }

        $this->tokens[] = new Token('comment', substr($this->template, $this->pos, $end + 4 - $this->pos));
        $this->pos = $end + 4;
    }

    private function scanRawEcho(): void
    {
        $end = strpos($this->template, '!!}', $this->pos);

        if ($end === false) {
            $this->tokens[] = new Token('text', substr($this->template, $this->pos));
            $this->pos = $this->length;

            return;
        }

        $content = substr($this->template, $this->pos + 3, $end - $this->pos - 3);
        $this->tokens[] = new Token('raw_echo', trim($content), ['expression' => trim($content)]);
        $this->pos = $end + 3;
    }

    private function scanEcho(): void
    {
        $end = strpos($this->template, '}}', $this->pos);

        if ($end === false) {
            $this->tokens[] = new Token('text', substr($this->template, $this->pos));
            $this->pos = $this->length;

            return;
        }

        $content = substr($this->template, $this->pos + 2, $end - $this->pos - 2);
        $this->tokens[] = new Token('echo', trim($content), ['expression' => trim($content)]);
        $this->pos = $end + 2;
    }

    private function scanDirective(): void
    {
        ++$this->pos; // skip @

        $name = $this->scanDirectiveName();

        $arguments = '';

        while ($this->pos < $this->length && ctype_space($this->template[$this->pos])) {
            ++$this->pos;
        }

        if ($this->pos < $this->length && $this->template[$this->pos] === '(') {
            $arguments = $this->scanBalancedExpression();
        }

        $this->tokens[] = new Token('directive', '@' . $name . $arguments, [
            'name' => $name,
            'arguments' => $arguments !== '' ? substr($arguments, 1, -1) : '',
        ]);
    }

    private function scanDirectiveName(): string
    {
        $start = $this->pos;

        while ($this->pos < $this->length && preg_match('/[A-Za-z_-]/', $this->template[$this->pos])) {
            ++$this->pos;
        }

        return substr($this->template, $start, $this->pos - $start);
    }

    private function scanBalancedExpression(): string
    {
        $start = $this->pos;
        $depth = 0;
        $inString = false;
        $stringChar = '';
        $escaped = false;

        while ($this->pos < $this->length) {
            $char = $this->template[$this->pos];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $stringChar) {
                    $inString = false;
                }
            } elseif ($char === '"' || $char === "'") {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === '(' || $char === '[' || $char === '{') {
                ++$depth;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                --$depth;

                if ($depth === 0 && $char === ')') {
                    ++$this->pos;

                    break;
                }
            }

            ++$this->pos;
        }

        return substr($this->template, $start, $this->pos - $start);
    }

    private function scanComponentTag(): void
    {
        $start = $this->pos;

        $isClosing = false;
        if (substr($this->template, $this->pos + 1, 1) === '/') {
            $isClosing = true;
        }

        $end = strpos($this->template, '>', $this->pos);

        if ($end === false) {
            $this->tokens[] = new Token('text', '<');
            ++$this->pos;

            return;
        }

        $tag = substr($this->template, $this->pos, $end + 1 - $this->pos);
        $this->pos = $end + 1;

        $this->tokens[] = new Token('component', $tag, array_merge(
            ['raw' => $tag],
            $this->parseComponentTag($tag)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function parseComponentTag(string $tag): array
    {
        if (preg_match('/<\/?x-([A-Za-z0-9_.-]+)([^>]*)>/s', $tag, $matches) !== 1) {
            return ['name' => '', 'attributes' => [], 'self_closing' => false, 'closing' => false];
        }

        $name = $matches[1];
        $closing = str_starts_with($tag, '</x-');
        $selfClosing = str_ends_with(rtrim($matches[2]), '/');
        $attrString = trim(preg_replace('/\/?$/', '', $matches[2]));

        return [
            'name' => $name,
            'attributes' => $this->parseAttributes($attrString),
            'self_closing' => $selfClosing,
            'closing' => $closing,
        ];
    }

    /**
     * @return array<string, array{type: 'literal'|'expression', value: string}>
     */
    private function parseAttributes(string $attrString): array
    {
        $attributes = [];
        $len = strlen($attrString);
        $i = 0;

        while ($i < $len) {
            // skip whitespace
            while ($i < $len && ctype_space($attrString[$i])) {
                ++$i;
            }

            if ($i >= $len) {
                break;
            }

            $expression = false;

            if ($attrString[$i] === ':') {
                $expression = true;
                ++$i;
            }

            $nameStart = $i;

            while ($i < $len && !ctype_space($attrString[$i]) && $attrString[$i] !== '=' && $attrString[$i] !== '>' && $attrString[$i] !== '/') {
                ++$i;
            }

            $name = substr($attrString, $nameStart, $i - $nameStart);

            if ($name === '') {
                ++$i;

                continue;
            }

            $value = '';
            $hasValue = false;

            if ($i < $len && ($attrString[$i] === '=' || $attrString[$i] === ':' || $attrString[$i] === '-')) {
                $hasValue = true;

                if ($attrString[$i] === ':') {
                    $expression = true;
                }

                $i = $this->skipBindingSuffix($attrString, $i);

                if ($i < $len && ($attrString[$i] === '"' || $attrString[$i] === "'")) {
                    $quote = $attrString[$i];
                    $valueStart = ++$i;

                    while ($i < $len && $attrString[$i] !== $quote) {
                        ++$i;
                    }

                    $value = substr($attrString, $valueStart, $i - $valueStart);

                    if ($i < $len) {
                        ++$i;
                    }
                } elseif ($i < $len) {
                    $valueStart = $i;

                    while ($i < $len && !ctype_space($attrString[$i]) && $attrString[$i] !== '>' && $attrString[$i] !== '/') {
                        ++$i;
                    }

                    $value = substr($attrString, $valueStart, $i - $valueStart);
                }
            }

            $attributes[$name] = ['type' => $expression ? 'expression' : 'literal', 'value' => $hasValue ? $value : true];
        }

        return $attributes;
    }

    private function skipBindingSuffix(string $attrString, int $i): int
    {
        $len = strlen($attrString);

        if ($i < $len && $attrString[$i] === ':') {
            ++$i;
        }

        if ($i < $len && $attrString[$i] === '-') {
            while ($i < $len && $attrString[$i] !== '=' && !ctype_space($attrString[$i]) && $attrString[$i] !== '>' && $attrString[$i] !== '/') {
                ++$i;
            }
        }

        if ($i < $len && $attrString[$i] === '=') {
            ++$i;
        }

        return $i;
    }

    private function scanText(): void
    {
        $start = $this->pos;

        while ($this->pos < $this->length) {
            $remaining = substr($this->template, $this->pos);

            if (str_starts_with($remaining, '{{--')
                || str_starts_with($remaining, '{!!')
                || str_starts_with($remaining, '{{')
                || str_starts_with($remaining, '@{{')
                || str_starts_with($remaining, '<x-')
                || str_starts_with($remaining, '</x-')
            ) {
                break;
            }

            if ($this->template[$this->pos] === '@' && $this->isDirectiveStart($remaining)) {
                break;
            }

            ++$this->pos;
        }

        $text = substr($this->template, $start, $this->pos - $start);

        if ($text !== '') {
            $this->tokens[] = new Token('text', $text);
        }
    }
}
