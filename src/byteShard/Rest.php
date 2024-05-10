<?php
/**
 * byteShard
 *
 * @category   byteShard Framework
 * @package    byteShard
 * @copyright  Copyright (c) 2009 - 2015 Bespin Studios GmbH - All Rights Reserved
 * @license    Unauthorized copying of this file, via any medium is strictly prohibited. Proprietary and confidential
 * @author     Lars Hennig <lars@bespin-studios.com>, January 2015
 * @version    1.0
 */

namespace byteShard;

use byteShard\Enum\RestMethod;
use byteShard\Rest\Exception\RestException;
use byteShard\Rest\Location;
use byteShard\Rest\Method;
use byteShard\Rest\RangeInterface;
use byteShard\Rest\RestEndpoint;
use Exception;
use Psr\Log\LoggerInterface;
use stdClass;

class Rest
{
    private RestMethod $method;
    /** @var array<string, Location> */
    private array            $locations         = [];
    private ?LoggerInterface $logger            = null;
    private ?string          $authenticatedUser = null;

    /**
     * Rest constructor.
     * @throws Exception
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $method = RestMethod::tryFrom(strtoupper($_SERVER['REQUEST_METHOD']));
        if ($method === null) {
            throw new Exception('invalid HTTP method '.$_SERVER['REQUEST_METHOD'].' must one of "'.implode('", "', array_column(RestMethod::cases(), 'value')).'"');
        } else {
            $this->method = $method;
        }
        $this->logger            = $logger;
        $this->authenticatedUser = $this->getBasicAuthUser();
    }

    /**
     * @param array<mixed>|string|false $token
     */
    public function authorize(array|string|false $token): void
    {
        if (array_key_exists('HTTP_BEARER', $_SERVER)) {
            $clientToken = $_SERVER['HTTP_BEARER'];
        } elseif (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
            $clientToken = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
        } else {
            $this->logger?->alert('SECURITY: ['.$_SERVER['HTTP_X_REAL_IP'].'] invalid access.');
            header("HTTP/1.1 401 Unauthorized");
            exit;
        }

        //TODO: bearer backend so multiple tokens are supported
        if (!is_string($token) || $token === '') {
            $this->logger?->alert('SECURITY: bearer not found.');
            header("HTTP/1.1 401 Unauthorized");
            exit;
        }

        if ($clientToken !== $token) {
            $this->logger?->alert('SECURITY: ['.$_SERVER['HTTP_X_REAL_IP'].'] unauthorized access.');
            header("HTTP/1.1 401 Unauthorized");
            exit;
        }
    }

    /**
     * @param Location ...$locations
     * @return $this
     */
    public function addLocation(Location ...$locations): Rest
    {
        foreach ($locations as $location) {
            if (!array_key_exists($location->getLocation(), $this->locations)) {
                $this->locations[$location->getLocation()] = $location;
            }
        }
        return $this;
    }

    public function getLocation(string $name): ?Location
    {
        if (array_key_exists($name, $this->locations)) {
            return $this->locations[$name];
        }
        return null;
    }

    public function call(string $location, string $className = ''): never
    {
        if ($className === '' && isset($this->locations[$location]) && $this->locations[$location]->isTypeSupported($this->method)) {
            $className = $this->locations[$location]->getClassName($this->method);
        }
        if (!empty($className)) {
            if (is_subclass_of($className, Method::class)) {
                $this->oldStyleCall($location, $className);
            } elseif (is_subclass_of($className, RestEndpoint::class)) {
                $this->newStyleCall($location, $className);
            }
        }
        $this->printClientResponse(response: ['state' => 'error', 'errorMessage' => 'unknown method'], httpResponseCode: 404);
    }

    private function oldStyleCall(string $location, string $className): void
    {
        header('Content-Type: application/json');
        $parameters          = $this->getParameters($location, false);
        $constructorCallback = $this->locations[$location]->getConstructorCallback($this->method);
        if ($constructorCallback === null) {
            $executeLocation = new $className();
        } else {
            $constructorParameters = $constructorCallback(['body' => $parameters, 'basic_auth_user' => $this->authenticatedUser]);
            if (is_array($constructorParameters)) {
                $executeLocation = new $className(...$constructorParameters);
            } else {
                $executeLocation = new $className($constructorParameters);
            }
        }
        if ($executeLocation instanceof Method) {
            $executeLocation->setRestMethod($this->method);
            try {
                $result = $executeLocation->run($parameters);
                if ($result === null) {
                    $this->printClientResponse(response: ['state' => 'error', 'errorMessage' => 'an error occurred'], httpResponseCode: 404);
                }
                $this->printClientResponse($result);
            } catch (Exception $e) {
                $this->printClientResponse(response: ['state' => 'error', 'errorMessage' => $e->getMessage()], httpResponseCode: 404);
            }
        }
    }

    private function newStyleCall(string $location, string $className): void
    {
        $parameters = $this->getParameters($location, true) ?? [];
        try {
            /** @var RestEndpoint $endpointObject */
            $endpointObject = new $className(...$parameters);
        } catch (Exception $e) {
            $this->logger?->error($e->getMessage());
            if ($e instanceof RestException) {
                $this->printClientResponse(response: ['state' => 'error', 'errorMessage' => $e->getMessage()], httpResponseCode: $e->getHttpResponseCode());
            }
            $this->printClientResponse(response: ['state' => 'error', 'errorMessage' => 'an error occurred'], httpResponseCode: 404);
        }

        $endpointObject->setLogger($this->logger);
        $endpointObject->setAuthenticatedUser($this->authenticatedUser);
        if ($endpointObject instanceof RangeInterface && array_key_exists('HTTP_RANGE', $_SERVER)) {
            $rangePrefix = $endpointObject->getRangePrefix();
            if (str_starts_with($_SERVER['HTTP_RANGE'], $rangePrefix.'=')) {
                $range = substr($_SERVER['HTTP_RANGE'], strlen($rangePrefix) + 1);
                //TODO: multipart ranges, check for commas
                $dashCount = substr_count($range, '-');
                if ($dashCount === 0) {
                    $from = filter_var($range, FILTER_VALIDATE_INT);
                    if ($from !== false) {
                        $endpointObject->setQueryRange($from);
                    }
                } elseif ($dashCount === 1) {
                    if (str_starts_with($range, '-')) {
                        $from = filter_var($range, FILTER_VALIDATE_INT);
                        if ($from !== false) {
                            $endpointObject->setQueryRange($from);
                        }
                    } else {
                        $parts = explode('-', $range);
                        $from  = filter_var($parts[0], FILTER_VALIDATE_INT);
                        $to    = filter_var($parts[1], FILTER_VALIDATE_INT);
                        if ($from !== false && $to !== false && $from < $to) {
                            $endpointObject->setQueryRange($from, $to);
                        }
                    }
                }
            }
        }
        try {
            $result = $endpointObject->run();
            $endpointObject->setResponseRangeHeaders();
            if ($result === null) {
                $this->printClientResponse(response: ['state' => 'error', 'errorMessage' => 'an error occurred'], httpResponseCode: 404);
            }
            $this->printClientResponse(response: $result, contentType: $endpointObject->getContentType(), httpResponseCode: $endpointObject->getReturnCode());
        } catch (Exception $e) {
            $this->logger?->error($e->getMessage());
            $this->printClientResponse(response: ['state' => 'error', 'errorMessage' => 'an error occurred'], httpResponseCode: 404);
        }
    }

    private function getParameters(string $location, bool $newStyle): mixed
    {
        if ($this->method === RestMethod::GET) {
            return $this->getGETParameters($location, $newStyle);
        }
        return $this->getBodyParameters($newStyle);
    }

    private function printClientResponse(mixed $response, string $contentType = 'application/json', int $httpResponseCode = 200): never
    {
        header('Content-Type: '.$contentType);
        if (is_string($response)) {
            http_response_code($httpResponseCode);
            print $response;
        } else {
            $jsonResponse = json_encode($response);
            if ($jsonResponse === false) {
                http_response_code(500);
                print 'Internal Server Error';
            } else {
                http_response_code($httpResponseCode);
                print $jsonResponse;
            }
        }
        exit;
    }

    private function getGETParameters(string $location, bool $getAssociativeArray = false): mixed
    {
        if (isset($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $bodyParameters);
            if (is_array($bodyParameters) && !empty($bodyParameters)) {
                $locationFound = false;
                $params        = $getAssociativeArray ? [] : new stdClass();
                foreach ($bodyParameters as $key => $value) {
                    if ($key === 'request' && $value === $location) {
                        $locationFound = true;
                    } else {
                        if ($getAssociativeArray === true && is_array($params)) {
                            $params[$key] = $value;
                        } else {
                            $params->{$key} = $value;
                        }
                    }
                }
                if ($locationFound === true) {
                    return $params;
                }
            }
        }
        return null;
    }

    /**
     * Using mixed as body object since json_decode returns mixed
     */
    private function getBodyParameters(bool $getAssociativeArray = false): mixed
    {
        $contentType = '';
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $contentType = $_SERVER['CONTENT_TYPE'];
        }
        $body = file_get_contents('php://input');
        if ($body !== false) {
            if (stripos($contentType, 'application/json') !== false) {
                $json = json_decode($body, $getAssociativeArray);
                if ($json === null && strlen($body) > 0) {
                    $this->printClientResponse(['state' => 'error', 'errorMessage' => 'invalid json payload']);
                }
                return $json;
            }
            if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($body, $bodyParameters);
                return $bodyParameters;
            }
        }
        if (stripos($contentType, 'multipart/form-data') !== false) {
            $body = [];
            if (!empty($_FILES)) {
                $body = $_FILES;
            }
            foreach ($_REQUEST as $name => $value) {
                if ($name !== 'request' && array_key_exists($name, $body) === false) {
                    $body[$name] = $value;
                }
            }
            return $body;
        }
        $this->printClientResponse(response: ['state' => 'error', 'errorMessage' => 'invalid content type: '.$contentType], httpResponseCode: 415);
    }

    private function getBasicAuthUser(): ?string
    {
        if (array_key_exists('REMOTE_USER', $_SERVER) && strlen($_SERVER['REMOTE_USER']) > 0) {
            return $_SERVER['REMOTE_USER'];
        }
        if (array_key_exists('REDIRECT_REMOTE_USER', $_SERVER) && strlen($_SERVER['REDIRECT_REMOTE_USER']) > 0) {
            return $_SERVER['REDIRECT_REMOTE_USER'];
        }
        return null;
    }
}
