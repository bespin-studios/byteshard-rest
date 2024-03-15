<?php

namespace byteShard\Rest;

use Psr\Log\LoggerInterface;

abstract class RestEndpoint
{
    private string             $contentType       = 'application/json';
    protected ?LoggerInterface $log               = null;
    private int                $returnCode        = 200;
    private ?string            $authenticatedUser = null;

    protected function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    protected function setReturnCode(int $returnCode): void
    {
        $this->returnCode = $returnCode;
    }

    public function getReturnCode(): int
    {
        return $this->returnCode;
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->log = $logger;
    }

    public function setAuthenticatedUser(string|null $authenticatedUser): void
    {
        $this->authenticatedUser = $authenticatedUser;
    }

    public function getAuthenticatedUser(): ?string
    {
        return $this->authenticatedUser;
    }

    abstract public function run(): mixed;
}
