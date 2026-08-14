<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class CreateRateLimitsTable extends Migration
{
    public function up(): void
    {
        schema()->create('rate_limits', function ($table): void {
            $table->string('key', 255);
            $table->integer('count', false, true)->default(0);
            $table->integer('reset_at', false, true);

            $table->unique('key');
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('rate_limits');
    }
}
