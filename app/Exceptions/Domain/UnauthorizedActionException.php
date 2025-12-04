<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

class UnauthorizedActionException extends DomainException
{
    protected string $errorCode = 'UNAUTHORIZED_ACTION';

    protected int $httpStatus = Response::HTTP_FORBIDDEN;

    public function __construct(
        string $message = 'You are not authorized to perform this action',
        ?string $errorCode = null,
        ?int $httpStatus = null,
    ) {
        parent::__construct($message, $errorCode, $httpStatus);
    }
}
