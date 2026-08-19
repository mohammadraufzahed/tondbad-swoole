# TondView / View Engine Audit

This document lists the structural, correctness, security, and usability problems found in the current TondView implementation (`src/View`, `public/tondview.js`, `config/view.php`, and related service providers). It is based on a read-through of the code and targeted reproductions against `TemplateCompiler` and `ComponentAttributeBag`.

## Executive summary

The engine compiles `.tond.php` files into classes in `TondbadSwoole\View\Compiled`, supports layouts/yields/sections, components, and a Livewire-style live component layer over HTTP/WebSocket/SSE. The overall shape is sound, but the **directive lexer is the single biggest correctness blocker**: it fails on the most common Blade/Blade-like syntax (`@if ($x)`, `@foreach ($items as $item)`, `@elseif`, `@forelse`, `@section`, etc.), treats `@` anywhere in text as a directive, and cannot parse an expression that contains `[]`/`{}` inside directive arguments. On top of that, the **live front-end reconnects itself in a loop** for WebSocket and SSE transports, the **live state token is not injected automatically**, class components cannot access `$slot`, and several helpers (`old`, `csrf_token`) are not Swoole-aware.

---

## 1. Compiler / Tokenizer (`src/View/Compilers/{TemplateCompiler,Tokenizer}.php`)

### 1.1 Directives with a space before `(` are silently broken

`scanDirective` reads the directive name, then immediately checks whether `$this->template[$this->pos] === '('`. It never skips whitespace. Any common formatting such as

```blade
@if ($show)
@foreach ($items as $item)
@forelse ($todos as $todo)
@elseif ($foo)
@section ('content')
@yield ('title')
```

leaves `arguments` empty. The `(` and expression are then emitted as plain text, and `compileExpression('')` returns `''`, producing invalid PHP such as:

```php
<?php if (''): ?> ($show)
<?php foreach (''): ?> ($items as $item)
```

This is **the most common user-facing bug** and breaks almost every real template.

### 1.2 `@` anywhere in text/HTML is parsed as a directive

`Tokenizer::scanText` stops as soon as it sees `@` followed by `[A-Za-z_]`. This means

```blade
contact us at foo@bar.com
```

compiles to:

```php
contact us at foo<?php /* @bar */ ?>.com
```

Any email, mention, CSS selector, or JS variable containing `@` is corrupted. There is no `@verbatim` escape hatch.

### 1.3 `scanBalancedExpression` does not track `[]` or `{}`

`scanBalancedExpression` only counts parentheses and strings. A directive such as

```blade
@if ($foo['bar'])
```

works only if there is no space before `(`; but even then, an argument like `@if (in_array($x, ['a','b']))` is parsed by `parseArguments` using `{([` depth, while `scanBalancedExpression` does not account for the `[`/`]` or `{`/`}` inside the parentheses. This can cut the expression early and produce parse errors or PHP warnings.

### 1.4 `@php` block compiles its body as normal template syntax

A `@php ... @endphp` block is wrapped with `<?php $content ?>`, but `$content` has already been through `compileTokens`. So

```blade
@php {{ $x }} @endphp
```

becomes:

```php
<?php  <?php echo e($x); ?>  ?>
```

which is a parse error. `@php` blocks should be treated as raw text until `@endphp`.

### 1.5 `@parent` is broken

`@parent` compiles to `$__ctx->parent('')` with an empty section name. `ViewContext::renderFinal` never populates `parentSections` because the parent layout is rendered in capture mode and `@yield` does not register a section. So `@parent` always returns an empty string. The whole section/parent inheritance mechanism needs a layout that uses `@section('name') ... @show` and a way to remember the current section name when `@parent` is compiled.

### 1.6 `@section` block closer names are awkward

`isBlockCloser` maps `hasSection` to `endhasSection` and `sectionMissing` to `endsectionMissing` (mixed camelCase). This is not documented and is not the natural `@endhasSection` / `@endsectionMissing` a user would write. More importantly, the block opener/closer matching in `compileTokens` is string-based and can be confused by nested blocks of the same type if the parser loses its place after a failed expression parse.

### 1.7 `compileExpression` is too naive

`compileExpression` returns an empty string literal `''` when the argument is empty, which turns missing directive arguments into invalid PHP (`if ('')`, `foreach ('')`, `switch ('')`). It should reject empty required arguments at compile time. It also does not validate or parenthesize expressions for `switch`/`for`/`while`, so a stray `;` can break generated PHP.

### 1.8 Component tag parser is regex-based and brittle

`parseComponentTag` uses `/<\/?x-([A-Za-z0-9_.-]+)([^>]*)>/s`. It:

- Breaks on `>` inside attribute values (`<x-foo title="A > B">`).
- Does not support boolean attributes cleanly (`disabled` becomes an empty string and renders `disabled=""`).
- Does not strip leading/trailing whitespace from attribute names.
- Does not support spread/merge syntax (`{{ $attributes }}`, `:class` arrays) in the tokenizer; `ComponentAttributeBag` tries to handle them at runtime.

### 1.9 `@yield` default truthiness bug

`compileDirective` for `yield` tests `$parts[1] ?? false`. A string default of `'0'` is falsy in PHP, so `@yield('title', '0')` would not pass the default. It should use `isset($parts[1])`.

---

## 2. Component / Attribute System (`src/View/{Component,AnonymousComponent,ComponentAttributeBag}.php`)

### 2.1 `$slot` is not injected into class-component views

For `<x-alert>Hello</x-alert>` rendered via `ViewManager::renderComponent`:

- Anonymous components get `'slot' => $slots['default']` in their view data.
- Class components do not. The `Component::$slots` array is populated by `withSlots`, but `Component::render()` returns a `View` manually and must remember to pass `$this->slot()` to the view data.

This means a class component view cannot use `$slot` out of the box; the author must wire it manually every time.

### 2.2 `__manager` leaks into the attribute bag and can crash `{{ $attributes }}`

`AnonymousComponent` passes `['__manager' => $this]` to the parent `Component::__construct`, which stores it in `attributes` because there is no `__manager` property. `AnonymousComponent::render()` then passes `attributes->toArray()` as the view data, and `ComponentAttributeBag::__toString()` calls `attributeString()` on that data. `attributeString` calls `e()` on each value, and `e($manager)` tries to cast a `ViewManager` object to string, producing:

```
Error: Object of class TondbadSwoole\View\ViewManager could not be converted to string
```

Reproduction:

```php
$b = new ComponentAttributeBag(['__manager' => new stdClass()]);
echo $b;
```

### 2.3 `ComponentAttributeBag::class()` crashes on a string `class` attribute

```php
$b = new ComponentAttributeBag(['class' => 'foo bar']);
echo $b->class(['baz']);
// TypeError: array_merge(): Argument #1 must be of type array, string given
```

HTML `class` is almost always a space-separated string, so `class()` is currently unusable for the common case.

### 2.4 `attributeString` does not handle array values

```php
echo new ComponentAttributeBag(['class' => ['foo', 'bar']]);
// class="Array" (with Array to string conversion warning)
```

Array attributes should be joined with a space, not cast to string.

### 2.5 Boolean attributes from component tags render as `disabled=""`

`<x-alert disabled>` parses `disabled` with an empty string value. `attributeString` treats an empty string as a regular value and emits `disabled=""`. HTML5 boolean attributes should be emitted as `disabled` when truthy.

### 2.6 Typed component properties receive raw string values from HTML attributes

`Component::__construct` assigns incoming data directly to public properties. If a component declares `public int $count` and the view passes a string `'5'`, PHP coerces it in property assignment, but more complex types (`bool`, `DateTime`, enum) will throw a `TypeError`. There is no casting layer for component attributes (unlike the ORM's casting).

### 2.7 `@props` has precedence issues and no separation from view data

`@props(['type' => 'info'])` compiles to:

```php
$__data = array_merge($__propsArray, $__data);
extract($__data, EXTR_SKIP);
```

Because `EXTR_SKIP` skips collisions, the order in the merged array determines precedence. If `array_merge` puts `$__propsArray` first and then `$__data`, the *props* take precedence, which is the opposite of what users expect (caller-supplied data should override defaults). The current generated code is actually `array_merge($props, $__data)`; the real bug is more subtle: if a key exists in both, the value from `$__data` wins in the array, but `extract` with `EXTR_SKIP` only processes each key once, so the first occurrence wins. The generated code needs to be `extract(array_merge($__data, $props), EXTR_SKIP)` so explicit props override data, or the semantics should be clearly defined and tested.

### 2.8 `ComponentRegistry` discovery is shallow and regex-based

`ComponentRegistry::discover` uses `DirectoryIterator` (non-recursive) and a regex that only works when `class` and `extends` are on the same line with `class X extends`. Components in subdirectories and classes with `extends` on a later line are missed.

### 2.9 Live components registered in `app/View/Components` are rendered as static components

`ComponentRegistry` maps both `Component` and `LiveComponent` subclasses. `ViewManager::renderComponent` checks `is_subclass_of($resolved, Component::class)`, which is true for `LiveComponent`, so `<x-demo-counter />` renders through the static component path and **never gets a state token**. The only supported entry point is `@live('demo-counter')`, but this is not enforced or documented in code.

---

## 3. Live Components (`src/View/Live/{LiveComponent,LiveComponentManager,LiveRender,LivePatcher,StateStore}.php`)

### 3.1 Live state token is not injected unless the view contains a magic placeholder

`LiveRender::injectState` only replaces the exact literal `<data-t-state></data-t-state>`. If the component author forgets it, `tondview.js` cannot find an `input[name="t:state"]` and live updates fail silently. The framework should either:

- Auto-append the hidden state input to the live root, or
- Throw a clear error if the placeholder is missing.

### 3.2 `LiveComponent::hydrate` drops `null` values

```php
public function hydrate(array $state): void
{
    $allowed = $this->publicSubclassProperties();
    foreach ($state as $key => $value) {
        if (isset($allowed[$key]) && !is_null($value)) {
            $this->$key = $value;
        }
    }
}
```

A nullable public property such as `public ?string $note = null` whose stored value is `null` will not be restored, and may keep stale data from the previous request. It should use `array_key_exists` and assign `null` when present.

### 3.3 `LiveComponent::runAction` passes `null` for missing required parameters

```php
$arguments[] = $params[$parameter->getName()]
    ?? $params[$index]
    ?? ($parameter->isOptional() ? $parameter->getDefaultValue() : null);
```

If an action method has a non-optional typed parameter and the request omits it, `null` is passed, causing a `TypeError`. Actions with parameters are effectively unsupported because `tondview.js` never sends `t:params` anyway.

### 3.4 Old state tokens are never deleted

`LiveComponentManager::finalize` creates a new token on every save but does not delete the previous token. `StateStore` accumulates keys in cache, and a replay of an old token still works.

### 3.5 `mount()` is called on every initial render, overriding constructor data

`LiveComponentManager::render` passes `$data` from `@live('name', $data)` to `Component::create`, then immediately calls `mount()`, which resets all public properties to defaults. Any data passed in `@live()` is silently lost. The `mount()` signature also accepts no arguments, so initialization from route data is impossible.

### 3.6 `LivePatcher` only supports full-root replacement

`LivePatcher::diff` compares the entire segment 0 and, if it changed, returns a single `replace` patch with the full new HTML. There is no per-element diff, no keyed morphing, and no support for multiple live segments per component. Every update tears down the whole component root, losing DOM state such as focus, selection, `<video>` playback, and `<canvas>` contents.

### 3.7 WebSocket connections are not shared across workers

`SseConnectionManager` uses `$server->sendMessage()` for cross-worker broadcast, but `WsConnectionManager` has no equivalent. A WebSocket client connected to worker A will not receive a state update triggered by an HTTP request handled on worker B.

### 3.8 SSE subscriptions are not authenticated

`ViewServiceProvider::boot` registers:

```php
$route->get('/_live/sse', [LiveSseController::class, 'handle']);
```

with no `Authenticate` or `VerifyCsrfToken` middleware. Any unauthenticated client can subscribe to `/_live/sse?component=any-name` and receive HTML patches containing potentially private component state.

### 3.9 WebSocket actions bypass CSRF

`WebSocket` frames cannot carry `X-CSRF-Token` from the browser `WebSocket` constructor. The current protocol sends `t:component`, `t:state`, and `t:action` in the first message without any CSRF token, so a live component protected by `VerifyCsrfToken` on the HTTP endpoint is exposed over the socket. Either the handshake must carry a token or the protocol must include one.

### 3.10 State is stored unencrypted in the cache

`StateStore` serializes the entire component state into whatever `CacheContract` is configured. There is no encryption, HMAC, or signature. If cache storage is Redis/file/SQLite and an attacker can read it, they can reconstruct component state.

---

## 4. Front-end Runtime (`public/tondview.js`)

### 4.1 WebSocket transport re-initializes in a loop

After the first message, `applyPatches` calls `initRoot` on the replacement element. If the transport is `websocket`, `initRoot` calls `connectWebSocket`, which opens a new socket and immediately sends `t:component` again. The server responds, `applyPatches` runs again, and the loop repeats. A real browser would create an unbounded number of WebSocket connections.

### 4.2 SSE transport also reconnects after every patch

`connectSse` creates an `EventSource`, and `applyPatches`/`initRoot` create a new `EventSource` after every server patch. Old event sources are not closed, so each live update leaks a connection and can trigger further reconnects.

### 4.3 `wire:click` actions are always sent over HTTP, never the active transport

`attachAction` always calls `updateHttp`, even when `transportFor(root)` is `websocket`. The WebSocket connection is therefore used only for the initial render handshake, not for actions, defeating the purpose of the transport selector.

### 4.4 `data-t-action` on a form is ignored

`attachForm` submits the form with `FormData` but never appends the action name from `data-t-action`. If a form wants to call an action other than the default sync, it cannot. `LiveComponentManager::update` only acts on `t:action`, so a form with `data-t-action="addTodo"` will simply re-render after `syncInputs`.

### 4.5 No `wire:model` / lazy / debounce support

There is no input-level synchronization. The only way to send model data is a full form submit. Livewire-style `wire:model` with `lazy`, `debounce`, `defer` modifiers is missing.

### 4.6 No action parameter parsing

`wire:click="increment(5)"` is not parsed. `sendAction(root, action)` sends the raw attribute string as `t:action` and never populates `t:params`. `LiveComponent::runAction` supports params but the front-end never provides them.

### 4.7 No `wire:key`, `wire:ignore`, `wire:loading`, `wire:poll`, `wire:stream`

Because every update is a full `outerHTML` replacement, the following are missing and are needed for a usable live system:

- `wire:key` for stable identity.
- `wire:ignore` to preserve DOM subtrees (maps, canvas, third-party widgets).
- `wire:loading` / `wire:target` loading states.
- `wire:poll` for periodic refresh.
- `wire:stream` for SSE streaming (the SSE transport exists but is only a broadcast receiver).

### 4.8 `applyPatches` can re-attach to the wrong component

When a component is replaced, `applyPatches` queries for the *first* `[data-t-live="${name}"]` element in the document. If two live components share the same name, events and listeners may jump between them.

### 4.9 Server errors replace the component with raw text

`updateHttp` calls `res.text()` and applies it as a `replace` patch, regardless of HTTP status. A 500 response body like `"Live update failed"` will be inserted as the new component HTML.

### 4.10 CSRF is read from a meta tag the framework does not inject

`tondview.js` reads `meta[name="t-csrf"]`, but the framework never injects this tag. The developer must remember to add it to every layout. If it is missing, live HTTP requests send an empty `X-CSRF-Token` and the `VerifyCsrfToken` middleware rejects them.

---

## 5. Helpers and Runtime (`src/Support/helpers.php`, `src/Http/Response.php`)

### 5.1 `old()` and `csrf_token()` are not Swoole-aware

```php
function old(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function csrf_token(): string
{
    return session_id() ?: bin2hex(random_bytes(16));
}
```

OpenSwoole does not populate `$_POST`/`$_GET` in the same way as PHP-FPM. `old()` should read from the current `Request`, not superglobals. `csrf_token()` should return the session guard's `antiCsrf` value, not `session_id()` or a random value. As written, `@csrf` emits a token that does not match the one validated by `VerifyCsrfToken`.

### 5.2 `csrf_token()` fallback is non-deterministic

When `session_id()` is empty (the normal case in Swoole), `csrf_token()` returns a new random string on every call, so a form using `@csrf` and the meta tag used by `tondview.js` will use different tokens.

---

## 6. Service Provider / Routing (`src/Providers/Default/ViewServiceProvider.php`)

### 6.1 Live component routes are not grouped or scoped

All live components share a single `POST /_live/{component}` route and a single `GET /_live/sse` stream. There is no authorization per component, no rate limiting, and no CSRF on SSE.

### 6.2 `ViewServiceProvider` only exposes `/tondview.js` from `public/tondview.js`

The runtime is served as a static file. There is no versioning, no cache-busting, and no minification/bundling pipeline.

### 6.3 `view.live.transport` config is not enforced at compile time

A view author can write `data-t-transport="websocket"` on a live component while the server is running with `VIEW_LIVE_TRANSPORT=http`. The back-end still exposes all endpoints, but the front-end makes assumptions that may fail (e.g., WebSocket loop bug).

---

## 7. Missing or incomplete features

The following are acceptable for a v1 if documented, but currently they are *missing and untested*:

| Feature | Status |
| --- | --- |
| `@verbatim` / `@endverbatim` | Missing |
| `@once` / `@pushOnce` | Missing |
| `@can` / `@cannot` / `@endcan` | Missing |
| `@checked` / `@selected` / `@disabled` | Missing |
| `@each` / `@includeFirst` / `@includeWhen` | Partial; `@includeWhen` condition not compiled |
| `wire:model` / `wire:poll` / `wire:stream` | Missing |
| `wire:key` / `wire:ignore` / `wire:loading` | Missing |
| View cache invalidation in long-running workers | Untested with opcache |
| Compiled view class garbage collection | Old compiled classes accumulate until `view:clear` |
| Multi-segment / partial live patches | Missing |
| Cross-worker WebSocket broadcast | Missing |
| Encrypted / signed live state | Missing |

---

## 8. Recommendations (high-level)

1. **Fix the directive lexer first.** `scanDirective` must skip whitespace before `(` and `parseArguments`/`scanBalancedExpression` must handle `[]`/`{}`/`()` together. This unblocks the whole template language.
2. **Make `@` parsing context-aware.** Only treat `@` as a directive when it is at a word boundary or the start of a token, and support `@verbatim`.
3. **Auto-inject the live state token.** Replace the `<data-t-state>` placeholder requirement with automatic injection at `LiveRender::wrap` or `LiveComponentManager::finalize`.
4. **Stop the WebSocket/SSE reconnect loops.** After applying a patch, rebind event listeners without creating a new transport connection; reuse the existing socket for actions.
5. **Route actions through the selected transport.** If `data-t-transport="websocket"`, send `t:action` messages over the socket; if `sse`, send actions over HTTP and use SSE only for broadcasts.
6. **Make `$slot` available to class-component views automatically** by passing the default slot closure into the view data.
7. **Fix `ComponentAttributeBag`**: handle string `class`, array attribute values, boolean attributes, and filter out `__manager`.
8. **Secure SSE/WebSocket**: add session/CSRF verification and per-component authorization before subscribing or sending actions.
9. **Implement `wire:model` and `wire:ignore`** before calling the live feature complete; without them, full-root replacement is too destructive for real apps.
10. **Make helpers Swoole-aware**: `old()` should read from `Request`, `csrf_token()` from the session guard.

---

## Files examined

- `src/View/Compilers/TemplateCompiler.php`
- `src/View/Compilers/Tokenizer.php`
- `src/View/Compilers/CompilerBlock.php`
- `src/View/ViewManager.php`
- `src/View/ViewContext.php`
- `src/View/Component.php`
- `src/View/AnonymousComponent.php`
- `src/View/ComponentAttributeBag.php`
- `src/View/ComponentRegistry.php`
- `src/View/Live/LiveComponent.php`
- `src/View/Live/LiveComponentManager.php`
- `src/View/Live/LiveRender.php`
- `src/View/Live/LivePatcher.php`
- `src/View/Live/StateStore.php`
- `src/View/Live/WsConnectionManager.php`
- `src/View/Live/SseConnectionManager.php`
- `src/View/Live/LiveComponentController.php`
- `src/View/Live/LiveSseController.php`
- `public/tondview.js`
- `src/Providers/Default/ViewServiceProvider.php`
- `src/Support/helpers.php`
- `config/view.php`
- `tests/Unit/View/TemplateCompilerTest.php`
- `tests/E2E/ViewEngineE2ETest.php`
- `tests/E2E/ViewEngineSseE2ETest.php`
- `tests/E2E/ViewEngineWebSocketE2ETest.php`
