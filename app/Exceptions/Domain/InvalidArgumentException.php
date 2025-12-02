<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception for invalid arguments or parameters.
 *
 * Use when:
 * - Required parameters are missing
 * - Parameter values are invalid (wrong type, out of range)
 * - Parameter combinations are invalid
 */
class InvalidArgumentException extends DomainException
{
    protected string $errorCode = 'INVALID_ARGUMENT';

    protected int $httpStatus = Response::HTTP_BAD_REQUEST;

    public function __construct(string $message = 'Invalid argument provided')
    {
        parent::__construct($message);
    }
}
