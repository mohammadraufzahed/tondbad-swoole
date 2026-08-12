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
            $table->integer('priority', false, true)->default(0);
            $table->integer('delay', false, true)->nullable();
            $table->string('backoff_type', 20)->nullable();
            $table->integer('backoff_value', false, true)->nullable();
            $table->integer('timeout', false, true)->nullable();
            $table->integer('progress', false, true)->default(0);
            $table->string('deduplication_id', 255)->nullable();
            $table->integer('parent_id', false, true)->nullable();
            $table->integer('children_count', false, true)->default(0);
            $table->integer('completed_children_count', false, true)->default(0);
            $table->string('status', 20)->default('waiting');
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('jobs');
    }
}
