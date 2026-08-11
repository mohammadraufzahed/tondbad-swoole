<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class CreateTondbadQueueJobsTable extends Migration
{
    public function up(): void
    {
        schema()->create('jobs', function ($table): void {
            $table->id();
            $table->string('queue', 255);
            $table->text('payload');
            $table->integer('attempts', false, true);
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at', false, true);
            $table->integer('created_at', false, true);
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('jobs');
    }
}
