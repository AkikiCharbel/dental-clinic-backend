<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

class ResourceConflictException extends DomainException
{
    protected string $errorCode = 'RESOURCE_CONFLICT';

    protected int $httpStatus = Response::HTTP_CONFLICT;

    public function __construct(string $message = 'The resource is in a conflicting state')
    {
        parent::__construct($message);
    }
}
