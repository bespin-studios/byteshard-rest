<?php

namespace byteShard\Rest\Exception;

use byteShard\Exception;
use Throwable;

class RestException extends Exception
{

    private int $httpResponseCode;

    public function __construct(string $message = '', int $code = 10000000, string $method = '', Throwable $previous = null, int $statusCode = 500)
    {
        $this->httpResponseCode = $statusCode;
        parent::__construct($message, $code, $method, $previous);
    }

    public function getHttpResponseCode(): int
    {
        return $this->httpResponseCode;
    }

    public function setHttpResponseCode(int $httpResponseCode): void
    {
        $this->httpResponseCode = $httpResponseCode;
    }

}