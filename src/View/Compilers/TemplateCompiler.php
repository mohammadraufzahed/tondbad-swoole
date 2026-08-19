<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Compilers;

use TondbadSwoole\View\ViewManager;

final class TemplateCompiler
{
    private Tokenizer $tokenizer;

    private int $forelseCounter = 0;

    public function __construct(private readonly ?ViewManager $manager = null)
    {
        $this->tokenizer = new Tokenizer();
    }

    public function compile(string $template, string $className = ''): string
    {
        $tokens = $this->tokenizer->tokenize($template);
        $body = $this->compileTokens($tokens);

        if ($className === '') {
            $className = '__View_' . md5($template);
        }

        return '<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Compiled;

class ' . $className . ' extends \TondbadSwoole\View\AbstractCompiledView
{
    /**
     * @param array<string, mixed> $__data
     */
    protected function renderInternal(array $__data, \TondbadSwoole\View\ViewContext $__ctx): void
    {
        extract($__data, EXTR_SKIP);
        ?>' . $body . '<?php
    }
}
';
    }

    private function compileTokens(array $tokens): string
    {
        $stack = [new CompilerBlock('root')];
        $count = count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            $current = $stack[count($stack) - 1];

            if ($token->data['consumed'] ?? false) {
                continue;
            }

            if ($token->type === 'directive') {
                $name = (string) ($token->data['name'] ?? '');
                $args = (string) ($token->data['arguments'] ?? '');

                if ($name === 'section' && $this->isSectionInline($args)) {
                    $current->append($this->wrapSectionInline($args));
                    continue;
                }

                if ($name === 'php' && $args !== '') {
                    $current->append('<?php ' . $args . ' ?>');
                    continue;
                }

                if ($this->isBlockOpener($name)) {
                    $stack[] = new CompilerBlock($name, $args);
                    continue;
                }

                if ($this->isBlockCloser($name, $current->type)) {
                    $block = array_pop($stack);
                    if ($block === null) {
                        continue;
                    }
                    $compiled = $this->wrapBlock($block);
                    $stack[count($stack) - 1]->append($compiled);
                    continue;
                }

                if ($name === 'empty' && $current->type === 'forelse') {
                    $current->append('__FORELSE_EMPTY__');
                    $current->state = 'empty';
                    continue;
                }

                if ($name === 'else' && $current->type === 'if') {
                    $current->append('<?php else: ?>');
                    continue;
                }

                if ($name === 'elseif' && $current->type === 'if') {
                    $args = (string) ($token->data['arguments'] ?? '');
                    if (trim($args) === '') {
                        throw new \InvalidArgumentException('The @elseif directive requires an expression.');
                    }
                    $current->append('<?php elseif (' . $this->compileExpression($args, false) . '): ?>');
                    continue;
                }

                $current->append($this->compileDirective($token));
                continue;
            }

            if ($token->type === 'component') {
                $current->append($this->compileComponent($token, $tokens, $i));
                continue;
            }

            if ($token->type === 'text') {
                $current->append($this->compileText($token->content));
                continue;
            }

            if ($token->type === 'echo') {
                $expr = $token->data['expression'] ?? '';
                $current->append('<?php echo e(' . $this->compileExpression($expr, false) . '); ?>');
                continue;
            }

            if ($token->type === 'raw_echo') {
                $expr = $token->data['expression'] ?? '';
                $current->append('<?php echo ' . $this->compileExpression($expr, false) . '; ?>');
                continue;
            }

            if ($token->type === 'comment') {
                continue;
            }
        }

        return $stack[0]->content;
    }

    private function isSectionInline(string $args): bool
    {
        return count($this->parseArguments($args)) >= 2;
    }

    private function wrapSectionInline(string $args): string
    {
        $parts = $this->parseArguments($args);
        $name = $parts[0] ?? '';
        $value = $parts[1] ?? '';

        return '<?php $__ctx->section(' . $this->valueArg($name) . ', function() { return ' . $this->compileExpression($value, false) . '; }); ?>';
    }

    private function isBlockOpener(string $name): bool
    {
        return in_array($name, [
            'if', 'unless', 'foreach', 'forelse', 'for', 'while', 'switch',
            'section', 'component', 'slot', 'push', 'php',
            'auth', 'guest', 'env', 'production',
            'hasSection', 'sectionMissing', 'error',
        ], true);
    }

    private function isBlockCloser(string $name, string $type): bool
    {
        $map = [
            'if' => 'endif',
            'unless' => 'endunless',
            'foreach' => 'endforeach',
            'forelse' => 'endforelse',
            'for' => 'endfor',
            'while' => 'endwhile',
            'switch' => 'endswitch',
            'section' => 'endsection',
            'component' => 'endcomponent',
            'slot' => 'endslot',
            'push' => 'endpush',
            'php' => 'endphp',
            'auth' => 'endauth',
            'guest' => 'endguest',
            'env' => 'endenv',
            'production' => 'endproduction',
            'hasSection' => 'endhasSection',
            'sectionMissing' => 'endsectionMissing',
            'error' => 'enderror',
        ];

        return ($map[$type] ?? '') === $name;
    }

    private function wrapBlock(CompilerBlock $block): string
    {
        $args = $block->args;
        $content = $block->content;

        if (in_array($block->type, ['if', 'unless', 'foreach', 'forelse', 'for', 'while', 'switch'], true) && trim($args) === '') {
            throw new \InvalidArgumentException("The @{$block->type} directive requires an expression.");
        }

        return match ($block->type) {
            'if' => '<?php if (' . $this->compileExpression($args, false) . '): ?>' . $content . '<?php endif; ?>',
            'unless' => '<?php if (!(' . $this->compileExpression($args, false) . ')): ?>' . $content . '<?php endif; ?>',
            'foreach' => '<?php foreach (' . $this->compileExpression($args, false) . '): ?>' . $content . '<?php endforeach; ?>',
            'forelse' => $this->wrapForelse($block),
            'for' => '<?php for (' . $this->compileExpression($args, false) . '): ?>' . $content . '<?php endfor; ?>',
            'while' => '<?php while (' . $this->compileExpression($args, false) . '): ?>' . $content . '<?php endwhile; ?>',
            'switch' => '<?php switch (' . $this->compileExpression($args, false) . '): ?>' . $content . '<?php endswitch; ?>',
            'section' => $this->wrapSection($block),
            'component' => $this->wrapComponent($block),
            'slot' => $this->wrapSlot($block),
            'push' => $this->wrapPush($block),
            'php' => '<?php ' . $content . ' ?>',
            'auth' => '<?php if (auth()?->check()): ?>' . $content . '<?php endif; ?>',
            'guest' => '<?php if (!auth()?->check()): ?>' . $content . '<?php endif; ?>',
            'env' => '<?php if (config(\'app.env\') === ' . $this->valueArg($args) . '): ?>' . $content . '<?php endif; ?>',
            'production' => '<?php if (config(\'app.env\') === \'production\'): ?>' . $content . '<?php endif; ?>',
            'hasSection' => '<?php if ($__ctx->hasSection(' . $this->valueArg($args) . ')): ?>' . $content . '<?php endif; ?>',
            'sectionMissing' => '<?php if (!$__ctx->hasSection(' . $this->valueArg($args) . ')): ?>' . $content . '<?php endif; ?>',
            'error' => '<?php if (!empty($errors[' . $this->valueArg($args) . '])): ?>' . $content . '<?php endif; ?>',
            default => $content,
        };
    }

    private function wrapForelse(CompilerBlock $block): string
    {
        ++$this->forelseCounter;
        $id = $this->forelseCounter;
        $expression = $this->compileExpression($block->args, false);
        [$foreachContent, $emptyContent] = $this->splitForelseContent($block->content);

        return "<?php \$__empty_{$id} = true; foreach ({$expression}): \$__empty_{$id} = false; ?>{$foreachContent}<?php endforeach; if (\$__empty_{$id}): ?>{$emptyContent}<?php endif; ?>";
    }

    private function splitForelseContent(string $content): array
    {
        $parts = explode('__FORELSE_EMPTY__', $content, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function wrapSection(CompilerBlock $block): string
    {
        $args = $this->parseArguments($block->args);
        $name = $args[0] ?? '';
        $default = $args[1] ?? null;

        if ($default !== null) {
            return '<?php $__ctx->section(' . $this->valueArg($name) . ', function() { return ' . $this->compileExpression($default, false) . '; }); ?>';
        }

        return '<?php $__ctx->section(' . $this->valueArg($name) . ', function() use ($__data, $__ctx) { extract($__data, EXTR_SKIP); ob_start(); ?>' . $block->content . '<?php return ob_get_clean(); }); ?>';
    }

    private function wrapComponent(CompilerBlock $block): string
    {
        $args = $this->parseArguments($block->args);
        $name = $args[0] ?? '';
        $data = $args[1] ?? '[]';

        return '<?php $__ctx->startComponent(' . $this->valueArg($name) . ', ' . $this->compileExpression($data, false) . '); $__ctx->slot("default", function() use ($__data, $__ctx) { extract($__data, EXTR_SKIP); ob_start(); ?>' . $block->content . '<?php return ob_get_clean(); }); $__ctx->endComponent(); ?>';
    }

    private function wrapSlot(CompilerBlock $block): string
    {
        $args = $this->parseArguments($block->args);
        $name = $args[0] ?? '';

        return '<?php $__ctx->slot(' . $this->valueArg($name) . ', function() use ($__data, $__ctx) { extract($__data, EXTR_SKIP); ob_start(); ?>' . $block->content . '<?php return ob_get_clean(); }); ?>';
    }

    private function wrapPush(CompilerBlock $block): string
    {
        $name = $this->parseArguments($block->args)[0] ?? '';

        return '<?php $__ctx->push(' . $this->valueArg($name) . ', function() use ($__data, $__ctx) { extract($__data, EXTR_SKIP); ob_start(); ?>' . $block->content . '<?php return ob_get_clean(); }); ?>';
    }

    private function compileDirective(Token $token): string
    {
        $name = (string) ($token->data['name'] ?? '');
        $args = (string) ($token->data['arguments'] ?? '');
        $parts = $this->parseArguments($args);

        return match ($name) {
            'extends' => '<?php $__ctx->layout(' . $this->valueArg($parts[0] ?? '') . '); ?>',
            'yield' => '<?php echo $__ctx->yieldSection(' . $this->valueArg($parts[0] ?? '') . (isset($parts[1]) ? ', ' . $this->compileExpression($parts[1], false) : '') . '); ?>',
            'parent' => '<?php echo $__ctx->parent(' . $this->valueArg($parts[0] ?? '') . '); ?>',
            'stack' => '<?php echo $__ctx->stack(' . $this->valueArg($parts[0] ?? '') . ($parts[1] ?? false ? ', ' . $this->valueArg($parts[1]) : '') . '); ?>',
            'include' => '<?php $__ctx->include(' . $this->valueArg($parts[0] ?? '') . ($parts[1] ?? false ? ', array_merge($__data, ' . $this->compileExpression($parts[1], false) . ')' : ', $__data') . '); ?>',
            'includeIf' => '<?php if (' . $this->compileExpression($parts[1] ?? 'true', false) . '): $__ctx->include(' . $this->valueArg($parts[0] ?? '') . (isset($parts[2]) ? ', ' . $this->compileExpression($parts[2], false) : '') . '); endif; ?>',
            'includeWhen' => '<?php if (' . $this->compileExpression($parts[0] ?? 'true', false) . '): $__ctx->include(' . $this->valueArg($parts[1] ?? '') . (isset($parts[2]) ? ', ' . $this->compileExpression($parts[2], false) : '') . '); endif; ?>',
            'json' => '<?php echo e(json_encode(' . $this->compileExpression($parts[0] ?? '', false) . ', ' . ($parts[1] ?? 'JSON_THROW_ON_ERROR') . ')); ?>',
            'csrf' => '<input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">',
            'method' => '<input type="hidden" name="_method" value="<?php echo e(' . $this->compileExpression($parts[0] ?? '', false) . '); ?>">',
            'inject' => '<?php ' . ($parts[0] ?? '') . ' = app()?->container->make(' . ($parts[1] ?? '') . '); ?>',
            'class' => '<?php echo e(\\classNames(' . $this->compileExpression($parts[0] ?? '[]', false) . ')); ?>',
            'attributes' => '<?php echo \\attributeString(' . $this->compileExpression($parts[0] ?? '[]', false) . '); ?>',
            'props' => '<?php $__data = array_merge(' . $this->compileExpression($parts[0] ?? '[]', false) . ', $__data); extract($__data, EXTR_SKIP); ?>',
            'break' => '<?php break; ?>',
            'continue' => '<?php continue; ?>',
            'case' => '<?php case ' . $this->compileExpression($parts[0] ?? '', false) . ': ?>',
            'default' => '<?php default: ?>',
            'php' => '<?php ' . $args . ' ?>',
            'live' => '<?php echo $__ctx->getManager()->renderLiveComponent(' . $this->valueArg($parts[0] ?? '') . ', ' . $this->compileExpression($parts[1] ?? '[]', false) . '); ?>',
            default => '<?php /* @' . $name . ' */ ?>',
        };
    }

    private function compileComponent(Token $token, array $tokens, int &$index): string
    {
        $name = (string) ($token->data['name'] ?? '');
        $attrs = $token->data['attributes'] ?? [];
        $closing = (bool) ($token->data['closing'] ?? false);
        $selfClosing = (bool) ($token->data['self_closing'] ?? false);

        if ($closing) {
            return '';
        }

        $dataArray = $this->buildComponentData($attrs);

        if ($selfClosing) {
            return $this->compileSelfClosingComponent($name, $dataArray);
        }

        [$innerTokens, $closingIndex] = $this->findComponentRange($tokens, $index);

        $index = $closingIndex;

        if ($name === 'slot') {
            $slotName = $attrs['name']['value'] ?? 'default';
            $innerCompiled = $this->compileTokens($innerTokens);

            return '<?php $__ctx->slot(' . var_export((string) $slotName, true) . ', function() use ($__data, $__ctx) { extract($__data, EXTR_SKIP); ob_start(); ?>' . $innerCompiled . '<?php return ob_get_clean(); }); ?>';
        }

        [$defaultCompiled, $slotOutputs] = $this->compileComponentChildren($innerTokens);

        $result = '<?php $__ctx->startComponent(' . var_export($name, true) . ', ' . $dataArray . ');';
        foreach ($slotOutputs as $slotOutput) {
            $result .= $slotOutput;
        }
        $result .= '$__ctx->slot("default", function() use ($__data, $__ctx) { extract($__data, EXTR_SKIP); ob_start(); ?>' . $defaultCompiled . '<?php return ob_get_clean(); }); $__ctx->endComponent(); ?>';

        return $result;
    }

    /**
     * @param list<Token> $tokens
     * @return array{0: string, 1: list<string>}
     */
    private function compileComponentChildren(array $tokens): array
    {
        $slotOutputs = [];
        $directiveStack = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $t = $tokens[$i];

            if ($t->type === 'component') {
                $cName = (string) ($t->data['name'] ?? '');
                $cClosing = (bool) ($t->data['closing'] ?? false);
                $cSelf = (bool) ($t->data['self_closing'] ?? false);

                if ($cClosing || $cSelf) {
                    continue;
                }

                if ($cName === 'slot' && $directiveStack === []) {
                    $slotName = $t->data['attributes']['name']['value'] ?? 'default';
                    $slotClosingIndex = $this->componentClosingIndex($tokens, $i);
                    $slotInner = [];
                    for ($j = $i + 1; $j < $slotClosingIndex; ++$j) {
                        $slotInner[] = $tokens[$j];
                    }
                    $slotInnerCompiled = $this->compileTokens($slotInner);
                    $slotOutputs[] = '<?php $__ctx->slot(' . var_export((string) $slotName, true) . ', function() use ($__data, $__ctx) { extract($__data, EXTR_SKIP); ob_start(); ?>' . $slotInnerCompiled . '<?php return ob_get_clean(); }); ?>';

                    for ($k = $i; $k <= $slotClosingIndex; ++$k) {
                        $tokens[$k]->data['consumed'] = true;
                    }

                    $i = $slotClosingIndex;
                    continue;
                }

                $i = $this->componentClosingIndex($tokens, $i);
                continue;
            }

            if ($t->type === 'directive') {
                $dName = (string) ($t->data['name'] ?? '');

                if ($this->isBlockOpener($dName)) {
                    $directiveStack[] = $dName;
                } else {
                    $current = $directiveStack === [] ? '' : $directiveStack[count($directiveStack) - 1];

                    if ($this->isBlockCloser($dName, $current)) {
                        array_pop($directiveStack);
                    }
                }
            }
        }

        return [$this->compileTokens($tokens), $slotOutputs];
    }

    /**
     * @param list<Token> $tokens
     */
    private function componentClosingIndex(array $tokens, int $startIndex): int
    {
        $name = (string) ($tokens[$startIndex]->data['name'] ?? '');
        $depth = 1;
        $count = count($tokens);

        for ($i = $startIndex + 1; $i < $count; ++$i) {
            $t = $tokens[$i];

            if ($t->type !== 'component') {
                continue;
            }

            $tName = (string) ($t->data['name'] ?? '');

            if ($tName !== $name) {
                continue;
            }

            $tClosing = (bool) ($t->data['closing'] ?? false);
            $tSelfClosing = (bool) ($t->data['self_closing'] ?? false);

            if ($tClosing) {
                --$depth;

                if ($depth === 0) {
                    return $i;
                }
            } elseif (!$tSelfClosing) {
                ++$depth;
            }
        }

        return $count - 1;
    }

    private function compileSelfClosingComponent(string $name, string $dataArray): string
    {
        if ($name === 'slot') {
            return '';
        }

        return '<?php $__ctx->startComponent(' . var_export($name, true) . ', ' . $dataArray . '); $__ctx->endComponent(); ?>';
    }

    /**
     * @param array<string, array{type: string, value: string}> $attrs
     */
    private function buildComponentData(array $attrs): string
    {
        $parts = [];

        foreach ($attrs as $attrName => $attr) {
            $key = var_export($attrName, true);

            if ($attr['type'] === 'expression') {
                $value = $this->compileExpression($attr['value'], false);
            } else {
                $value = var_export($attr['value'], true);
            }

            $parts[] = $key . ' => ' . $value;
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param list<Token> $tokens
     * @return array{0: list<Token>, 1: int}
     */
    private function findComponentRange(array $tokens, int $startIndex): array
    {
        $closingIndex = $this->componentClosingIndex($tokens, $startIndex);
        $inner = [];

        for ($i = $startIndex + 1; $i < $closingIndex; ++$i) {
            $inner[] = $tokens[$i];
        }

        $tokens[$closingIndex]->data['consumed'] = true;

        return [$inner, $closingIndex];
    }

    private function arrayToExpression(array $data): string
    {
        $parts = [];

        foreach ($data as $key => $value) {
            $parts[] = var_export($key, true) . ' => ' . $value;
        }

        return '[' . implode(', ', $parts) . ']';
    }

    private function compileText(string $text): string
    {
        return $text;
    }

    private function compileExpression(string $expression, bool $escape): string
    {
        $expression = trim($expression);

        if ($expression === '') {
            return "''";
        }

        if (preg_match('/^\'[^\']*\'$/', $expression) || preg_match('/^"[^"]*"$/', $expression)) {
            return $expression;
        }

        return $expression;
    }

    private function valueArg(string $arg): string
    {
        $arg = trim($arg);

        if ($arg === '') {
            return "''";
        }

        if ((str_starts_with($arg, "'") && str_ends_with($arg, "'")) || (str_starts_with($arg, '"') && str_ends_with($arg, '"'))) {
            return $arg;
        }

        return $this->compileExpression($arg, false);
    }

    private function parseArguments(string $arguments): array
    {
        $arguments = trim($arguments);

        if ($arguments === '') {
            return [];
        }

        $parts = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = '';
        $escaped = false;
        $len = strlen($arguments);

        for ($i = 0; $i < $len; ++$i) {
            $char = $arguments[$i];

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
            }

            if ($char === ',' && $depth === 0 && !$inString) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $parts[] = trim($current);

        return $parts;
    }
}
