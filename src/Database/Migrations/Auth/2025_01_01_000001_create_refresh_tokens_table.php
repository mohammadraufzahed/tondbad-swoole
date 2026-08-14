<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class CreateRefreshTokensTable extends Migration
{
    public function up(): void
    {
        schema()->create('refresh_tokens', function ($table): void {
            $table->id();
            $table->string('session_id', 36);
            $table->string('family', 36);
            $table->bigInteger('parent')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->bigInteger('used_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->bigInteger('expires_at', false, true);
            $table->bigInteger('created_at', false, true);

            $table->index('session_id');
            $table->index('family');
            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('refresh_tokens');
    }
}
