<?php

namespace App\Exceptions;

use RuntimeException;

class LastTenantAdminProtectedException extends RuntimeException
{
    public function __construct(string $message = 'A tenant must always have at least one tenant admin.')
    {
        parent::__construct($message);
    }
}
