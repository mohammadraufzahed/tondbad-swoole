<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class CreateQueuePausesTable extends Migration
{
    public function up(): void
    {
        schema()->create('queue_pauses', function ($table): void {
            $table->string('queue', 255);
            $table->boolean('paused')->default(false);
            $table->integer('created_at', false, true);
            $table->integer('updated_at', false, true);

            $table->unique('queue');
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('queue_pauses');
    }
}
