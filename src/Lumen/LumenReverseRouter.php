<?php

namespace Mathrix\OpenAPI\Assertions\Lumen;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenAPIValidation\PSR7\OperationAddress;
use UnexpectedValueException;
use function FastRoute\simpleDispatcher;

/**
 * Class LumenReverseRouter.
 *
 * @author Mathieu Bour <mathieu@mathrix.fr>
 * @copyright Mathrix Education SA.
 * @since 0.9.0
 */
class LumenReverseRouter
{
    /** @var Dispatcher $dispatcher The Lumen Dispatcher */
    private $dispatcher;


    /**
     * Get the Lumen Dispatcher
     *
     * @return Dispatcher
     */
    protected function getDispatcher(): Dispatcher
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
                foreach (app()->router->getRoutes() as $route) {
                    $r->addRoute($route["method"], $route["uri"], $route["action"]);
                }
            });
        }

        return $this->dispatcher;
    }


    /**
     * Dispatch an uri.
     *
     * @param string $method The method.
     * @param string $uri The uri.
     *
     * @return array
     */
    protected function dispatch(string $method, string $uri): array
    {
        $method = mb_strtoupper($method);

        return $this->getDispatcher()->dispatch($method, $uri);
    }


    /**
     * Get the OpenAPI URI based on the actual request URI.
     * This method far from perfect, but should work in most of cases.
     *
     * @link https://stackoverflow.com/questions/56352531/reverse-routing-in-lumen
     *
     * @param string $method The HTTP method (GET, POST, PUT, PATCH, DELETE etc.)
     * @param string $actualUri The actual request URI
     *
     * @return string
     */
    public function getUri(string $method, string $actualUri)
    {
        $currentRouter = $this->dispatch($method, $actualUri);

        $filteredRoutes = Collection::make(app()->router->getRoutes())
            ->map(function ($routeData) {
                // Remove parameters regex constraints
                $routeData["uri"] = preg_replace('/{([a-zA-Z]+)(:.*)}/', '{$1}', $routeData["uri"]);
                return $routeData;
            })
            ->reject(function ($routeData, $routeKey) use ($method) {
                // Reject routes which do not match the method
                return !Str::startsWith($routeKey, strtoupper($method));
            })
            ->reject(function ($routeData) use ($currentRouter, $method, $actualUri) {
                // Reject routes which do not match the controller/action
                if (!isset($routeData["action"]["uses"]) || !isset($currentRouter[1]["uses"])) {
                    return true;
                }

                return $currentRouter[1]["uses"] !== $routeData["action"]["uses"];
            })
            ->reject(function ($routeData) use ($actualUri) {
                // Reject routes which have not the same amount of parts (split by "/")
                $actualParts = explode("/", trim($actualUri, "/"));
                $routeParts = explode("/", trim($routeData["uri"], "/"));

                return count($actualParts) !== count($routeParts);
            })
            ->reject(function ($routeData, $routeKey) use ($currentRouter, $actualUri) {
                // Reject routes which does not have the same arguments
                $paramsKeys = array_map(function ($param) {
                    return "{{$param}}";
                }, array_keys($currentRouter[2]));

                $resolvedUri = str_replace(
                    $paramsKeys,
                    array_values($currentRouter[2]),
                    $routeData["uri"]
                );

                return $resolvedUri !== $actualUri;
            });

        if ($filteredRoutes->isEmpty()) {
            return $actualUri;
        } else if ($filteredRoutes->count() === 1) {
            $match = $filteredRoutes->first();

            return $match["uri"];
        } else {
            throw new UnexpectedValueException("Found more than one route matching the given criteria.");
        }
    }


    /**
     * Get the OperationAddress Object, to validate ServerRequest and Response against OpenAPI specification.
     *
     * @param Request $request The Illuminate HTTP Request.
     *
     * @return OperationAddress
     */
    public function getOperation(Request $request)
    {
        $operationUri = $this->getUri(
            $request->getMethod(),
            $request->getPathInfo() // Path info = without query string
        );

        return new OperationAddress($operationUri, strtolower($request->getMethod()));
    }
}
