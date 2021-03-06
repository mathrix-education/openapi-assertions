<?php

declare(strict_types=1);

namespace Mathrix\OpenAPI\Assertions\Lumen;

use cebe\openapi\spec\OpenApi;
use Illuminate\Http\Response;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use function app;
use function implode;
use function realpath;

/**
 * Trait LumenOpenAPIAssertions.
 *
 * @mixin Assert
 */
trait LumenOpenAPIAssertions
{
    /** @var string The OpenAPI specification path. */
    protected static $openAPISpecificationPath;
    /** @var ServerRequestValidator $requestValidator */
    protected static $requestValidator;
    /** @var ResponseValidator $responseValidator */
    protected static $responseValidator;
    /** @var LumenReverseRouter $reverseRouter */
    protected static $reverseRouter;
    /** @var OpenApi $schema */
    protected static $schema;

    /**
     * Boot the LumenOpenAPIAssertions.
     */
    public static function bootLumenOpenAPIAssertions(): void
    {
        // Check the specification existence
        if (self::$openAPISpecificationPath === null) {
            self::fail('LumenOpenAPIAssertions::$openAPISpecificationPath is not specified!');
        } elseif (realpath(self::$openAPISpecificationPath) === false) {
            self::fail('LumenOpenAPIAssertions::$openAPISpecificationPath does not exist (tried '
                . self::$openAPISpecificationPath . ')');
        }

        // Build validators
        $builder = (new ValidatorBuilder())->fromYamlFile(self::$openAPISpecificationPath);

        self::$requestValidator  = $builder->getServerRequestValidator();
        self::$responseValidator = $builder->getResponseValidator();
        self::$schema            = self::$requestValidator->getSchema();

        // Make reverse router
        self::$reverseRouter = new LumenReverseRouter();
    }

    /**
     * Convert Illuminate Response to PSR Response.
     *
     * @param Response $response The Illuminate Response.
     *
     * @return PsrResponseInterface
     */
    protected static function convertIlluminateToPsr($response): PsrResponseInterface
    {
        // Convert Illuminate HTTP Response to PSR-17 Response
        $psr17Factory   = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        return $psrHttpFactory->createResponse($response);
    }

    /**
     * Assert that the response matches the OpenAPI specification.
     *
     * @param Response $response The Lumen Response
     */
    public static function assertOpenAPIResponse($response): void
    {
        $app       = app();
        $operation = self::$reverseRouter->getOperation($app['request']);

        self::assertOpenAPI($response, $operation);
    }

    /**
     * Assert that the response matches the OpenAPI specification.
     *
     * @param Response         $response  The Lumen Response
     * @param OperationAddress $operation The Operation Address.
     */
    public static function assertOpenAPI($response, OperationAddress $operation): void
    {
        if (!$response instanceof ResponseInterface) {
            $psrResponse = self::convertIlluminateToPsr($response);
        } else {
            $psrResponse = $response;
        }

        try {
            self::assertNull(self::$responseValidator->validate($operation, $psrResponse));
        } catch (ValidationFailed $exception) {
            // Iterate over exceptions to build the failure message.
            $messages = [$exception->getMessage()];

            while ($exception->getPrevious() !== null) {
                $exception  = $exception->getPrevious();
                $messages[] = $exception->getMessage();
            }

            self::fail(implode("\n", $messages));
        }
    }
}
