<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class CreateIdentitiesTable extends Migration
{
    public function up(): void
    {
        schema()->create('identities', function ($table): void {
            $table->id();
            $table->string('user_id', 255);
            $table->string('provider', 64);
            $table->string('provider_user_id', 255);
            $table->string('email', 255)->nullable();
            $table->string('name', 255)->nullable();
            $table->json('claims');
            $table->bigInteger('created_at', false, true);
            $table->bigInteger('updated_at', false, true);

            $table->unique(['provider', 'provider_user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('identities');
    }
}
