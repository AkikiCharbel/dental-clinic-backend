<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

class ResourceNotFoundException extends DomainException
{
    protected string $errorCode = 'RESOURCE_NOT_FOUND';

    protected int $httpStatus = Response::HTTP_NOT_FOUND;

    public function __construct(string $message = 'The requested resource was not found')
    {
        parent::__construct($message);
    }
}
