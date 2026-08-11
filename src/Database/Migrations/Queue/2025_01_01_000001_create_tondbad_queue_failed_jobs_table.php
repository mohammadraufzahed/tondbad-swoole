<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class CreateTondbadQueueFailedJobsTable extends Migration
{
    public function up(): void
    {
        schema()->create('failed_jobs', function ($table): void {
            $table->id();
            $table->string('connection', 255);
            $table->string('queue', 255)->nullable();
            $table->text('payload');
            $table->text('exception');
            $table->integer('failed_at', false, true);
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('failed_jobs');
    }
}
