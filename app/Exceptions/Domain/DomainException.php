<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Exception;
use Symfony\Component\HttpFoundation\Response;

abstract class DomainException extends Exception
{
    protected string $errorCode = 'DOMAIN_ERROR';

    protected int $httpStatus = Response::HTTP_BAD_REQUEST;

    public function __construct(
        string $message = '',
        ?string $errorCode = null,
        ?int $httpStatus = null,
    ) {
        parent::__construct($message);

        if ($errorCode !== null) {
            $this->errorCode = $errorCode;
        }

        if ($httpStatus !== null) {
            $this->httpStatus = $httpStatus;
        }
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
