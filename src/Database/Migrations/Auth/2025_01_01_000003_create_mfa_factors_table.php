<?php

declare(strict_types=1);

use TondbadSwoole\Database\Migrations\Migration;

class CreateMfaFactorsTable extends Migration
{
    public function up(): void
    {
        schema()->create('mfa_factors', function ($table): void {
            $table->id();
            $table->string('user_id', 255);
            $table->string('type', 32);
            $table->text('config');
            $table->boolean('enabled')->default(true);
            $table->bigInteger('created_at', false, true);
            $table->bigInteger('updated_at', false, true);

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('mfa_factors');
    }
}
