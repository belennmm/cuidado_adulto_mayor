<?php

namespace App\Support;

use RuntimeException;

class OutboundRequestException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('External request unavailable or blocked by security policy.');
    }
}
