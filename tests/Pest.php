<?php

declare(strict_types=1);

use TondbadSwoole\Tests\Unit\TestCase;

pest()->extend(TestCase::class)->in('Unit');
pest()->extend(TestCase::class)->in('E2E');
