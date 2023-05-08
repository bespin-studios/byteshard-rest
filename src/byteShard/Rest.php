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
use byteShard\Rest\Location;
use byteShard\Rest\Method;
use Exception;
use stdClass;

class Rest
{
    private RestMethod $method;
    /** @var array<string, Location> */
    private array  $locations = [];
    private bool   $error     = false;
    private string $errorMessage;

    /**
     * Rest constructor.
     * @throws Exception
     */
    public function __construct()
    {
        $method = RestMethod::tryFrom(strtoupper($_SERVER['REQUEST_METHOD']));
        if ($method === null) {
            throw new Exception('invalid HTTP method '.$_SERVER['REQUEST_METHOD'].' must one of "'.implode('", "', array_column(RestMethod::cases(), 'value')).'"');
        } else {
            $this->method = $method;
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

    public function call(string $location): void
    {
        header('Content-Type: application/json');
        if (isset($this->locations[$location]) && $this->locations[$location]->isTypeSupported($this->method)) {
            $className = $this->locations[$location]->getClassName($this->method);
            if ($className !== null && is_subclass_of($className, Method::class)) {
                if ($this->method === RestMethod::GET) {
                    $parameters = $this->getGETParameters($location);
                } else {
                    $parameters = $this->getBodyParameters();
                }
                $constructorCallback = $this->locations[$location]->getConstructorCallback($this->method);
                if ($constructorCallback === null) {
                    $executeLocation = new $className();
                } else {
                    $constructorParameters = $constructorCallback($parameters);
                    if (is_array($constructorParameters)) {
                        $executeLocation = new $className(...$constructorParameters);
                    } else {
                        $executeLocation = new $className($constructorParameters);
                    }
                }
                if ($executeLocation instanceof Method) {
                    $executeLocation->setRestMethod($this->method);
                    if ($this->error === true) {
                        print json_encode(['state' => 'error', 'errorMessage' => $this->errorMessage]);
                        exit;
                    }
                    try {
                        $result = $executeLocation->run($parameters['body']);
                        if (is_string($result)) {
                            print $result;
                            exit;
                        }
                        if (is_array($result) || is_object($result)) {
                            print json_encode($result);
                            exit;
                        }
                        if ($result === null) {
                            http_response_code(404);
                            print json_encode(['state' => 'error', 'errorMessage' => 'an error occurred']);
                            exit;
                        }
                    } catch (Exception $e) {
                        print json_encode(['state' => 'error', 'errorMessage' => $e->getMessage()]);
                        exit;
                    }
                }
            }
            print json_encode(['state' => 'error', 'errorMessage' => 'invalid method']);
            exit;
        }
        print json_encode(['state' => 'error', 'errorMessage' => 'unknown method']);
        exit;
    }

    /**
     * @return array{body: null|object, basic_auth_user: null|string}
     */
    private function getGETParameters(string $location): array
    {
        if (isset($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $bodyParameters);
            if (is_array($bodyParameters) && !empty($bodyParameters)) {
                $locationFound = false;
                $params        = new stdClass();
                foreach ($bodyParameters as $key => $value) {
                    if ($key === 'request' && $value === $location) {
                        $locationFound = true;
                    } else {
                        $params->{$key} = $value;
                    }
                }
                if ($locationFound === true) {
                    return ['body' => $params, 'basic_auth_user' => $this->getBasicAuthUser()];
                }
            }
        }
        return ['body' => null, 'basic_auth_user' => $this->getBasicAuthUser()];
    }

    /**
     * Using mixed as body object since json_decode returns mixed
     * @return array{body: mixed, basic_auth_user: null|string}
     */
    private function getBodyParameters(): array
    {
        $contentType = '';
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $contentType = $_SERVER['CONTENT_TYPE'];
        }
        $body = file_get_contents('php://input');
        if ($body !== false) {
            if (stripos($contentType, 'application/json') !== false) {
                $json = json_decode($body);
                if ($json === null && strlen($body) > 0) {
                    $this->error        = true;
                    $this->errorMessage = 'invalid json payload';
                    return ['body' => false, 'basic_auth_user' => $this->getBasicAuthUser()];
                }
                return ['body' => $json, 'basic_auth_user' => $this->getBasicAuthUser()];
            }
            if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($body, $bodyParameters);
                return ['body' => $bodyParameters, 'basic_auth_user' => $this->getBasicAuthUser()];
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
            return ['body' => $body, 'basic_auth_user' => $this->getBasicAuthUser()];
        }
        print json_encode(['state' => 'error', 'errorMessage' => 'invalid content type: '.$contentType]);
        exit;
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
