<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class CreateScheduledJobsTable extends Migration
{
    public function up(): void
    {
        schema()->create('scheduled_jobs', function ($table): void {
            $table->string('id', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->json('trigger_config');
            $table->string('timezone', 64)->nullable();
            $table->json('job_config');
            $table->string('misfire_policy', 32)->default('smart');
            $table->integer('max_attempts', false, true)->default(1);
            $table->json('backoff')->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 32)->default('active');
            $table->datetime('next_run_at')->nullable();
            $table->datetime('last_run_at')->nullable();
            $table->text('last_run_result')->nullable();
            $table->datetime('locked_until')->nullable();
            $table->string('node_id', 255)->nullable();
            $table->string('locked_run_key', 255)->nullable();
            $table->integer('run_count', false, true)->default(0);
            $table->integer('fail_count', false, true)->default(0);
            $table->integer('version', false, true)->default(0);
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->string('between_start', 8)->nullable();
            $table->string('between_end', 8)->nullable();
            $table->boolean('unless_between')->default(false);
            $table->integer('without_overlapping_lease', false, true)->nullable();
            $table->boolean('run_in_background')->default(false);
            $table->string('output_path', 1024)->nullable();
            $table->integer('rate_limit_max', false, true)->nullable();
            $table->integer('rate_limit_window', false, true)->nullable();
            $table->string('queue', 255)->nullable();
            $table->string('connection', 255)->nullable();
            $table->json('data')->nullable();
            $table->datetime('created_at')->nullable();
            $table->datetime('updated_at')->nullable();

            $table->primary('id');
            $table->index('next_run_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('scheduled_jobs');
    }
}
