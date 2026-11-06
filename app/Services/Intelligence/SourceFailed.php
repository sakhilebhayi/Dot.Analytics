<?php

namespace App\Services\Intelligence;

use RuntimeException;

/** A source could not be read this time. Whatever was already stored stays as it is. */
class SourceFailed extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null, public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
    }
}
