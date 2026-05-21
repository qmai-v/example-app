<?php

namespace App\Exceptions;

use RuntimeException;

class MemberAlreadyExistsException extends RuntimeException
{
    public function __construct(string $message = 'This user is already a member of the tenant.')
    {
        parent::__construct($message);
    }
}
