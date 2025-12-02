<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception for operations that failed to complete.
 *
 * Use when:
 * - External service calls fail
 * - File operations fail
 * - Queue job processing fails
 * - Any operation that couldn't be completed (but is retryable)
 */
class OperationFailedException extends DomainException
{
    protected string $errorCode = 'OPERATION_FAILED';

    protected int $httpStatus = Response::HTTP_SERVICE_UNAVAILABLE;

    public function __construct(
        string $message = 'The operation could not be completed',
        protected bool $retryable = true,
    ) {
        parent::__construct($message);
    }

    /**
     * Check if the operation can be retried.
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
