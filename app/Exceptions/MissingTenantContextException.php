<?php

namespace App\Exceptions;

use RuntimeException;

class MissingTenantContextException extends RuntimeException
{
    public function __construct(string $message = 'No active tenant is set on the current TenantContext.')
    {
        parent::__construct($message);
    }
}
