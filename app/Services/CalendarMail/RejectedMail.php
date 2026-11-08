<?php

namespace App\Services\CalendarMail;

use RuntimeException;

class RejectedMail extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
