<?php

declare(strict_types=1);

use TondbadSwoole\View\Compilers\TemplateCompiler;

beforeEach(function () {
    $this->compiler = new TemplateCompiler();
});

it('compiles echo expressions escaped', function () {
    $compiled = $this->compiler->compile('Hello, {{ $name }}!');

    expect($compiled)->toContain("echo e(\$name)")
        ->and($compiled)->toContain('Hello,');
});

it('compiles raw echo expressions unescaped', function () {
    $compiled = $this->compiler->compile('Hello, {!! $name !!}!');

    expect($compiled)->toContain('echo $name');
});

it('compiles conditional blocks', function () {
    $compiled = $this->compiler->compile('@if($show)Visible@endif');

    expect($compiled)->toContain('if ($show):')
        ->and($compiled)->toContain('endif;');
});

it('compiles foreach loops', function () {
    $compiled = $this->compiler->compile('@foreach($items as $item){{ $item }}@endforeach');

    expect($compiled)->toContain('foreach ($items as $item):')
        ->and($compiled)->toContain('endforeach;')
        ->and($compiled)->toContain('echo e($item)');
});

it('compiles forelse with empty block', function () {
    $compiled = $this->compiler->compile('@forelse($items as $item){{ $item }}@emptyNo items@endforelse');

    expect($compiled)->toContain('foreach')
        ->and($compiled)->toContain('empty');
});

it('compiles component tags', function () {
    $compiled = $this->compiler->compile('<x-alert type="warning">Hello</x-alert>');

    expect($compiled)->toContain("startComponent('alert'")
        ->and($compiled)->toContain("'type' => 'warning'")
        ->and($compiled)->toContain('endComponent');
});

it('compiles dynamic component attributes', function () {
    $compiled = $this->compiler->compile('<x-alert :type="$level" />');

    expect($compiled)->toContain("'type' => \$level")
        ->and($compiled)->toContain('endComponent');
});

it('compiles sections and yields', function () {
    $compiled = $this->compiler->compile("@extends('app')@section('title','Home')@section('content')Body@endsection");

    expect($compiled)->toContain("layout('app')")
        ->and($compiled)->toContain("section('title'")
        ->and($compiled)->toContain("section('content'");
});
