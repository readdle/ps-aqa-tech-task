<?php

declare(strict_types=1);

namespace App\Service;

final class AppMode
{
    public function __construct(
        private readonly string $mode = 'healthy',
    ) {
    }

    public function isBroken(): bool
    {
        return 'broken' === strtolower($this->mode);
    }
}
