<?php

namespace App\Exceptions;

use RuntimeException;

final class FeedPublishLockException extends RuntimeException
{
    public function __construct(
        string $message = 'Une publication est déjà en cours sur ce flux.',
        public readonly ?int $holderUserId = null,
        public readonly ?string $holderName = null,
    ) {
        parent::__construct($message);
    }
}
