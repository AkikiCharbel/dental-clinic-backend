<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Testing\TestResponse;
use Osteel\OpenApi\Testing\ValidatorBuilder;
use Osteel\OpenApi\Testing\ValidatorInterface;
use PHPUnit\Framework\Assert;

trait ValidatesOpenApiSpec
{
    protected static ?ValidatorInterface $openApiValidator = null;

    protected function getOpenApiValidator(): ValidatorInterface
    {
        if (self::$openApiValidator === null) {
            $specPath = $this->getOpenApiSpecPath();

            if (! file_exists($specPath)) {
                Assert::markTestSkipped('OpenAPI spec not found. Run "php artisan scribe:generate" first.');
            }

            self::$openApiValidator = ValidatorBuilder::fromYaml($specPath)->getValidator();
        }

        return self::$openApiValidator;
    }

    protected function getOpenApiSpecPath(): string
    {
        return storage_path('app/scribe/openapi.yaml');
    }

    /**
     * Assert that the response matches the OpenAPI specification.
     */
    protected function assertResponseMatchesOpenApiSpec(TestResponse $response, string $path, string $method = 'get'): void
    {
        $validator = $this->getOpenApiValidator();

        $result = $validator->validate(
            $response->baseResponse,
            $path,
            $method,
        );

        Assert::assertTrue(
            $result,
            "Response does not match OpenAPI specification for {$method} {$path}",
        );
    }

    /**
     * Assert that the request body matches the OpenAPI specification.
     */
    protected function assertRequestMatchesOpenApiSpec(TestResponse $response, string $path, string $method = 'post'): void
    {
        $this->assertResponseMatchesOpenApiSpec($response, $path, $method);
    }

    /**
     * Make a GET request and validate against OpenAPI spec.
     */
    protected function getAndValidate(string $uri, array $headers = []): TestResponse
    {
        $response = $this->getJson($uri, $headers);

        if ($this->shouldValidateOpenApi()) {
            $this->assertResponseMatchesOpenApiSpec($response, $uri, 'get');
        }

        return $response;
    }

    /**
     * Make a POST request and validate against OpenAPI spec.
     */
    protected function postAndValidate(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $response = $this->postJson($uri, $data, $headers);

        if ($this->shouldValidateOpenApi()) {
            $this->assertResponseMatchesOpenApiSpec($response, $uri, 'post');
        }

        return $response;
    }

    /**
     * Make a PUT request and validate against OpenAPI spec.
     */
    protected function putAndValidate(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $response = $this->putJson($uri, $data, $headers);

        if ($this->shouldValidateOpenApi()) {
            $this->assertResponseMatchesOpenApiSpec($response, $uri, 'put');
        }

        return $response;
    }

    /**
     * Make a PATCH request and validate against OpenAPI spec.
     */
    protected function patchAndValidate(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $response = $this->patchJson($uri, $data, $headers);

        if ($this->shouldValidateOpenApi()) {
            $this->assertResponseMatchesOpenApiSpec($response, $uri, 'patch');
        }

        return $response;
    }

    /**
     * Make a DELETE request and validate against OpenAPI spec.
     */
    protected function deleteAndValidate(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $response = $this->deleteJson($uri, $data, $headers);

        if ($this->shouldValidateOpenApi()) {
            $this->assertResponseMatchesOpenApiSpec($response, $uri, 'delete');
        }

        return $response;
    }

    /**
     * Determine if OpenAPI validation should be performed.
     */
    protected function shouldValidateOpenApi(): bool
    {
        return file_exists($this->getOpenApiSpecPath())
            && env('VALIDATE_OPENAPI', true);
    }
}
