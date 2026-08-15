<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Relations;

use TondbadSwoole\Database\Model;

class MorphPlaceholder extends Model
{
    protected ?string $table = 'morph_placeholders';

    protected array $fillable = [];

    public bool $timestamps = false;
}
