# TondView — Template Engine / Live Fragment Proposal (v1)

## Summary

Add a full-featured, compiled PHP template engine to Tondbād Swoole. It uses Blade-style syntax in `.tond.php` view files and class-based/anonymous components, compiles to plain PHP into `storage/cache/views/`, and adds server-driven "live" fragments over **HTTP (HTMX), WebSocket, and Server-Sent Events**. No new Composer dependencies, no Tree-sitter or custom grammar, and no long-lived client state — live state is serialized to the existing cache store under a random token.

Ideas merged from the requested stacks:

| Stack | What we adopt |
|-------|---------------|
| **Phoenix LiveView / HEEx** | Assign-model templates (`$this->title`), component slots, attribute interpolation, static/dynamic `LiveRender` segments with change tracking, keyed comprehensions, server-side state per connection. |
| **Laravel Blade / Livewire** | Directives (`@if`, `@foreach`, `@section`, `@component`, `@live`), compiled PHP output, `wire:` reactivity (`wire:model`, `wire:click`, `wire:submit`, `wire:loading`), `$attributes` bag, `$slot`, `@error`. |
| **Django Templates / HTMX** | Sandboxed default expression rules inside `{{ }}`, progressive enhancement, server-rendered fragments returned over HTTP, clean separation of presentation from logic. |

## Non-negotiable decisions

1. **Source extension**: `.tond.php`. Compiled output lives in `storage/cache/views/` as `.php` files with a hashed path name.
2. **Compiler**: regex/character-scanner tokenizer. No Tree-sitter, no `ext-dom` requirement, no custom grammar in v1.
3. **Live transports**: HTTP (HTMX fallback), WebSocket, and SSE are all first-class. The active transport is chosen at runtime by `tondview.js` reading `data-t-transport` on each component (default from `config/view.php`).
4. **State storage**: live-component state is serialized to the existing cache (`HybridStore`/cache module) under a random `t:state` token. WebSocket connections keep an in-memory hot copy keyed by `fd` and write back to cache after every action and on `on('close')`.
5. **Escaping**: `{{ }}` is always escaped with `htmlspecialchars` (`ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5`). Raw output uses `{!! !!}`. There is no auto-escaping bypass.
6. **Dependencies**: zero new Composer packages. HTMX core is vendored as a static asset in `public/vendor/htmx/` (or loaded from CDN); `tondview.js` is our own small transport/patch runtime.
7. **Server**: the `serve` command uses `OpenSwoole\WebSocket\Server` whenever live fragments are enabled, because it handles both HTTP and WebSocket on the same port. When live fragments are disabled, the existing `OpenSwoole\HTTP\Server` is used. SSE is served over the same HTTP port.
8. **OpenSwoole safety**: per-render `ViewContext`, no global static section stacks, compiled templates extract data with `EXTR_SKIP` so internal variables cannot be clobbered. WebSocket/SSE handlers run inside a fresh per-message coroutine context and clear framework context after each message.

## Explicit v1 feature inventory

This section lists **every** feature that ships in v1. Nothing is dropped or deferred without an explicit v2 note.

### 1. Directives and syntax

| Feature | Syntax / note |
|---------|---------------|
| Escaped echo | `{{ $var }}` |
| Raw echo | `{!! $raw !!}` |
| Literal braces | `@{{` prints `{{` |
| Comments | `{{-- comment --}}` |
| Raw PHP block | `@php ... @endphp` |
| Inline PHP | `@php($x = 1)` |
| If / elseif / else | `@if(...)`, `@elseif(...)`, `@else`, `@endif` |
| Unless | `@unless(...) ... @endunless` |
| Switch | `@switch($x) @case(1) ... @break @default ... @endswitch` |
| Foreach | `@foreach($items as $item) ... @endforeach` |
| Forelse | `@forelse($items as $item) ... @empty ... @endforelse` |
| For | `@for($i = 0; $i < 10; $i++) ... @endfor` |
| While | `@while($cond) ... @endwhile` |
| Include | `@include('partial')`, `@include('partial', ['x' => 1])`, `@includeIf`, `@includeWhen`, `@includeFirst` |
| Extends (inheritance) | `@extends('layout')` |
| Section | `@section('name') ... @endsection` or `@section('name', 'value')` |
| Yield | `@yield('name')`, `@yield('name', 'default')` |
| Parent section | `@parent` inside `@section` to prepend/append to parent content |
| Stacks | `@push('scripts') ... @endpush` + `@stack('scripts')` |
| Service injection | `@inject('cache', CacheInterface::class)` into the view data |
| JSON output | `@json($data)` and `@json($data, JSON_PRETTY_PRINT)` |
| Conditional classes | `@class(['active' => $active, 'disabled' => $disabled])` |
| Conditional attributes helper | `@attributes(['disabled' => $disabled])` prints ` disabled` or nothing |
| CSRF / method | `@csrf` and `@method('PUT')` |
| Auth / env helpers | `@auth`, `@guest`, `@env('testing')`, `@production` |
| Errors | `@error('field') ... @enderror` |
| Section checks | `@hasSection('title')`, `@sectionMissing('title')` |
| Live component render | `@live('Counter', ['start' => 0])` equivalent to a live `<x-counter>` |

### 2. Expressions and sandboxing

- Inside `{{ }}` only a safe expression subset is allowed by default: variable access, property/array access, arithmetic/string operators, ternary, and a configurable whitelist of functions (`count`, `strtoupper`, `json_encode`, etc.).
- `{!! !!}` and `@php` are full-PHP zones.
- `@json` always calls `json_encode` with `JSON_THROW_ON_ERROR` and escapes the output.

### 3. Components

| Feature | Note |
|---------|------|
| Class-based components | `App\View\Components\Alert` extends `TondbadSwoole\View\Component` |
| Anonymous components | `resources/views/components/alert.tond.php` with `@props` |
| Auto-discovery | `app/View/Components` and `resources/views/components` are scanned by `ViewServiceProvider` |
| Explicit registration | `#[AsComponent('my-alert')]` on component classes and `config/view.php` component map |
| Component tag usage | `<x-alert type="warning" class="mb-4"> ... </x-alert>` |
| Self-closing | `<x-input name="email" />` |
| Props | `@props(['type' => 'info', 'dismissible' => false])` in anonymous component views |
| Public properties | class-based component constructor/public props become template variables |
| Default slot | `$slot` string variable inside the component view |
| Named slots | `<x-slot name="header"> ... </x-slot>` inside the block; accessed as `$header` inside the component view |
| Scoped slots | `<x-slot name="row" :item="$item">` — the component passes `$row` as a closure that renders with the provided data |
| Attributes bag | `$attributes` is a `ComponentAttributeBag` with `merge`, `class`, `except`, `only` |
| Conditional classes on tag | `<div @class(['active' => $active])>` compiles to a `class` attribute string |
| Boolean attributes | `<input type="checkbox" :checked="$checked">` compiles to `checked` or omitted based on truthiness |
| Attribute prefixing | `:foo="$bar"` compiles to a dynamic attribute `foo="<?php echo e($bar); ?>"`; `foo="bar"` is a literal string |
| Component nesting | Components can render other components; parser tracks nesting depth |

### 4. Layouts, sections, inheritance

- `@extends('layout')` is compiled to set the layout before rendering.
- `@section`/`@endsection` captures content into the per-render `ViewContext`.
- `@yield` renders a section or default.
- `@parent` merges child and parent section content.
- `@push`/`@stack` append/render stacked blocks.

### 5. View manager, helpers, and HTTP integration

| Feature | Note |
|---------|------|
| View helper | `view('welcome', ['name' => 'Tond'])` returns a `View` object; `view()->render()` returns the string |
| Response helper | `response()->view('welcome', $data)` compiles and ends the response |
| Route helper | `Route::view('/welcome', 'welcome', ['name' => 'Guest'])` |
| Automatic render | `HandlerInvoker` will render a returned `View` object if the response has not ended |
| Shared data | `View::share('appName', 'Tond')` / `view()->share(...)` |
| Composers | `View::composer('profile', function (View $view) { ... })` runs before rendering that view |
| Discovered paths | `resources/views`, `app/View/Components` (configurable via `config/view.php`) |
| Compiled cache | `storage/cache/views` by default; cleared by `view:clear`; warmed by `view:cache` |
| Config | `config/view.php` with `paths`, `compiled`, `components`, `cache_enabled`, `live` settings |

### 6. Live fragments (`wire:`)

| Directive | Behaviour |
|-----------|-----------|
| `wire:model="field"` | Sends action on `input changed delay:250ms` and updates `public $field`. |
| `wire:model.live="field"` | Same as `wire:model` with `delay:100ms`. |
| `wire:model.debounce.500ms="field"` | Uses `delay:500ms`. |
| `wire:model.lazy="field"` | Triggers on `change` instead of `input`. |
| `wire:click="method"` | Sends action `method` and re-renders. |
| `wire:submit="method"` | Form submit sends all form inputs plus action `method`. |
| `wire:loading` | Shows element while a component request is in flight. |
| `wire:loading.attr="disabled"` | Disables the element during loading. |
| `wire:poll.2s` | Re-fetches the component every 2 seconds over HTTP. |
| `wire:stream` | Subscribes to an SSE stream for server-pushed updates. |

### 7. Live transports

All three transports are supported and selectable per component or globally:

- **HTTP (HTMX fallback)**: `wire:` directives compile to transport-agnostic `data-t-*` attributes; `tondview.js` (in HTTP mode) adds `hx-post` pointing at `POST /_live/{component}`. The server returns the full component fragment; HTMX swaps `outerHTML`.
- **WebSocket**: `serve` runs `OpenSwoole\WebSocket\Server`. Client connects once per page to `ws://host:port/_live/ws?session=SESSION`. Each `wire:` interaction sends a JSON message; the server returns a JSON patch or full HTML; `tondview.js` applies the update.
- **SSE**: `GET /_live/sse?session=SESSION` returns `text/event-stream`. Client `wire:` actions still use HTTP, and the server pushes updates to all SSE connections subscribed to the same session. `wire:stream` subscribes to the SSE stream.

## Architecture

```
src/View
├── Compilers
│   ├── TemplateCompiler.php       # orchestrates compile
│   ├── Tokenizer.php              # regex/scanner tokens
│   └── ComponentTagRewriter.php   # <x-...> tag rewrite
├── Component.php                  # base class
├── ComponentRegistry.php          # name -> class / anonymous view
├── AnonymousComponent.php         # adapter for .tond.php files in components/
├── ComponentAttributeBag.php
├── ViewContext.php                # sections, stacks, slots, layout, live session per render
├── ViewManager.php                # compile/render/composer/share
├── View.php                       # value object, Renderable
├── ViewServiceProvider.php
├── Facades
│   └── View.php
├── helpers.php                    # view(), e(), csrf_token(), old()
├── Live
│   ├── LiveComponent.php
│   ├── LiveComponentController.php
│   ├── LiveComponentManager.php   # mount, hydrate, dispatch, render for all transports
│   ├── StateStore.php             # cache-backed token state
│   ├── WsConnectionManager.php    # fd -> session/component mapping
│   ├── SseConnectionManager.php   # session -> response + channel mapping
│   ├── LiveRender.php             # static + dynamic segments
│   ├── LivePatcher.php            # diff two LiveRender objects
│   ├── WireDirectiveRewriter.php  # wire: -> data-t-* attributes
│   └── Events
│       └── LiveComponentEvent.php # broadcast updates to SSE/WS
public/vendor/htmx/htmx.min.js   # optional, used for HTTP fallback
public/tondview.js               # transport selector + LiveRender patch applier
```

### Compilation output shape

Each `.tond.php` compiles to a class file in `storage/cache/views/{hash}.php`:

```php
<?php declare(strict_types=1);

namespace TondbadSwoole\View\Compiled;

class __View_a1b2c3 extends \TondbadSwoole\View\AbstractCompiledView
{
    protected function renderInternal(array $__data, \TondbadSwoole\View\ViewContext $__ctx): string
    {
        extract($__data, EXTR_SKIP);

        $__ctx->layout('layout');
        $__ctx->section('title', static fn () => 'Welcome');
        $__ctx->section('content', static function () use ($__ctx, $__data): void {
            extract($__data, EXTR_SKIP);
?>
            <h1>Hello, <?php echo e($name); ?></h1>
<?php if ($showList ?? false): ?>
            <ul>
<?php foreach ($items as $item): ?>
                <li><?php echo e($item); ?></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
<?php
        });

        return $__ctx->renderFinal($__data);
    }
}
```

`ViewManager` creates a fresh `ViewContext` per `render()` call.

### Live rendering and diffing

For `LiveComponent` templates, the compiler is run in **live mode** and wraps dynamic segments with `<!--t:d:0-->...<!--/t:d:0-->` markers. The render returns a `LiveRender` object:

```php
$render = new LiveRender(
    static: ['<h1>', '</h1><p>', '</p>'],
    dynamic: [
        fn () => e($this->title),
        fn () => e($this->message),
    ],
);
```

If a dynamic expression is inside an attribute value or an otherwise unwrappable position, that whole tag or component root is treated as one dynamic segment. `LivePatcher::diff($old, $new)` produces a JSON array where unchanged segments are `null` and changed segments are the new string. The client `tondview.js` applies the diff by replacing the content between the two comment markers. If the patch cannot be applied safely, the client falls back to replacing the entire component root `innerHTML`.

### WebSocket flow

1. `serve` runs `OpenSwoole\WebSocket\Server`.
2. On initial HTTP request, `LiveComponentManager` mounts the component, generates a state token, renders `LiveRender`, and serializes it to HTML with markers.
3. The component root is wrapped with `<div data-t-live="Counter" data-t-id="c-TOKEN" data-t-transport="websocket" data-t-session="SESSION">` plus a hidden `t:state` input.
4. `tondview.js` opens `ws://host:port/_live/ws?session=SESSION`.
5. On `wire:click="increment"`, `tondview.js` sends:
   ```json
   {"t:component":"Counter","t:id":"c-TOKEN","t:state":"...","t:action":"increment"}
   ```
6. `WsConnectionManager` routes the message to `LiveComponentManager`.
7. `LiveComponentManager` loads state, calls `increment()`, re-renders, produces a `LiveRender`, and returns:
   ```json
   {"type":"patch","id":"c-TOKEN","segments":[null,"2",null]}
   ```
8. `tondview.js` applies the patch. If the action calls `$this->redirect('/done')`, the server sends `{"type":"redirect","url":"/done"}`.

WebSocket connections sync state to cache after every action and on `on('close')`.

### SSE flow

1. Client opens `EventSource` at `/_live/sse?session=SESSION`.
2. `SseConnectionManager` registers the `OpenSwoole\HTTP\Response` and a `Coroutine\Channel` for that session. The SSE handler coroutine loops, writing SSE frames from the channel.
3. `wire:` actions use HTTP `POST /_live/{component}?session=SESSION`.
4. After the action, `LiveComponentManager` dispatches `LiveComponentEvent`.
5. `SseConnectionManager` pushes an SSE frame to every response registered for the session:
   ```
   event: patch
   data: {"id":"c-TOKEN","segments":[null,"updated"]}
   
   ```
6. `wire:stream` opens the SSE channel and listens for `stream` events.

### State store

```php
$token = bin2hex(random_bytes(16));
$state = new LiveState($componentName, $publicProperties, $errors, $session);
$cache->set("live:{$token}", serialize($state), ttl: 3600);
```

The cache store is the existing `HybridStore`/cache module. WebSocket connections keep a hot copy keyed by `fd` and write back to cache after every action and on close.

### Security per transport

- **HTTP**: `POST /_live/{component}` goes through the normal middleware stack, including `VerifyCsrfToken`. The state token is a hidden input.
- **WebSocket**: the `on('Open')` request carries headers and cookies; the `WsConnectionManager` validates `Origin` against `config('app.url')` and verifies the `t:state` token for every message. CSRF is enforced by requiring a signed `t:state` token that was generated during server rendering.
- **SSE**: `EventSource` sends the session query parameter and cookies; `SseConnectionManager` validates the session token on connection.

## Implementation phases

### Phase A — Core compiler and ViewManager
- `Tokenizer` and `TemplateCompiler`.
- `ViewContext` with sections, stacks, layout, and a per-request `liveSession` UUID.
- `ViewManager` with view discovery, compile cache, `share()`, `composer()`.
- `Response::view()`, `Route::view()`, `view()`, `e()`, `csrf_token()`, `old()`.
- `HandlerInvoker` renders returned `View` objects when the response has not ended.
- `view:cache` and `view:clear` commands.

### Phase B — Components
- `Component`, `ComponentRegistry`, `AnonymousComponent`, `ComponentAttributeBag`.
- `<x-...>` tag parser and `@props`.
- `$attributes` merging, conditional classes, boolean attributes.
- Named and scoped slots.

### Phase C — HTTP live fragments (HTMX baseline)
- `LiveComponent`, `StateStore`, `LiveComponentController`.
- `POST /_live/{component}` endpoint.
- `WireDirectiveRewriter` converts `wire:*` to `data-t-*` transport-agnostic attributes.
- `tondview.js` HTTP mode adds `hx-post`/`hx-target`/`hx-swap` for HTMX.
- `@csrf`, `@method`, `@error`, `old()`.
- Vendored HTMX in `public/vendor/htmx/`.

### Phase D — WebSocket support
- `HttpServerBuilder` updated to optionally build `OpenSwoole\WebSocket\Server`.
- `WsConnectionManager` and `/_live/ws` WebSocket handler.
- `LiveComponentManager` WebSocket message dispatch.
- `tondview.js` WebSocket transport and patch applier.

### Phase E — SSE support
- `SseConnectionManager` and `GET /_live/sse` endpoint using `OpenSwoole\Coroutine\Channel`.
- `wire:poll` over HTTP and `wire:stream` over SSE.
- `LiveComponentEvent` broadcast to SSE clients.

### Phase F — Live diffing
- Compile live component templates in live mode with `<!--t:d:N-->` markers.
- `LiveRender` and `LivePatcher`.
- WebSocket/SSE send segment diffs; HTTP/HTMX continues to send full fragments.

### Phase G — Documentation and benchmarks
- `docs/views.md` covering directives, components, live fragments, transports, JS setup, and CSRF.
- `ViewEngineBenchmark`.
- `php -l`, `composer validate --strict`, `composer test` green.

### Phase H — E2E testing
- `tests/E2E/ViewE2ETest.php`: HTTP layout, sections, includes, components.
- `tests/E2E/LiveComponentHttpE2ETest.php`: HTMX `wire:model`/`wire:click`.
- `tests/E2E/LiveComponentWebSocketE2ETest.php`: WebSocket mount and patch.
- `tests/E2E/LiveComponentSseE2ETest.php`: SSE stream and push.

## Verification checklist

- `find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l`
- `php composer.phar validate --strict`
- `php composer.phar test` — all unit and E2E tests pass.
- `php bin/tondbad benchmark benchmarks/ViewEngineBenchmark.php` runs without errors.
- `php bin/tondbad view:cache` followed by `php bin/tondbad serve`, then HTTP, WebSocket, and SSE E2E suites succeed.

## Future extension points (explicitly v2)

- **HEEx / Tree-sitter grammar**: an optional `.heex` parser with compile-time HTML validation and richer change tracking. Not in v1 because it adds a grammar dependency.
- **Morphdom-style DOM patching**: replace segment comment markers with a vendored `morphdom`-like algorithm for arbitrary HTML updates.
- **Scoped slot `let` variables**: pass data back to parent slot content.
- **HTML-aware attribute merging**: use `ext-dom` (optional) for stricter validation of `<x-` tags.
