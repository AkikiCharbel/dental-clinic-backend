<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

class BusinessRuleViolationException extends DomainException
{
    protected string $errorCode = 'BUSINESS_RULE_VIOLATION';

    protected int $httpStatus = Response::HTTP_UNPROCESSABLE_ENTITY;

    public function __construct(string $message = 'A business rule was violated')
    {
        parent::__construct($message);
    }
}
