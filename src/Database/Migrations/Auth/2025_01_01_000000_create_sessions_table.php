<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class CreateSessionsTable extends Migration
{
    public function up(): void
    {
        schema()->create('sessions', function ($table): void {
            $table->string('id', 36);
            $table->string('user_id', 255);
            $table->json('claims');
            $table->string('anti_csrf', 64)->nullable();
            $table->string('device', 255)->nullable();
            $table->string('family', 36)->nullable();
            $table->string('status', 20)->default('active');
            $table->bigInteger('expires_at', false, true);
            $table->bigInteger('created_at', false, true);

            $table->primary('id');
            $table->index('user_id');
            $table->index('family');
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('sessions');
    }
}
