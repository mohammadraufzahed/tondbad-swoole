<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

AppFactory::create(dirname(__DIR__))->run();
