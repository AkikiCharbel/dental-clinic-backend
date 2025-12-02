<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception for domain-level validation failures.
 *
 * Use when:
 * - Business rules validation fails (not form validation)
 * - Complex validation that requires domain knowledge
 * - Cross-field or cross-entity validation failures
 *
 * Note: For form/request validation, use Laravel's ValidationException.
 */
class ValidationException extends DomainException
{
    protected string $errorCode = 'DOMAIN_VALIDATION_FAILED';

    protected int $httpStatus = Response::HTTP_UNPROCESSABLE_ENTITY;

    /**
     * @param  array<string, array<string>>  $errors
     */
    public function __construct(
        string $message = 'Domain validation failed',
        protected array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Get validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
