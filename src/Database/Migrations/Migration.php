<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Migrations;

abstract class Migration
{
    abstract public function up(): void;

    abstract public function down(): void;
}
