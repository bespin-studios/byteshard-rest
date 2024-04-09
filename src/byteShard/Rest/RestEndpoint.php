<?php

namespace byteShard\Rest;

use Psr\Log\LoggerInterface;

abstract class RestEndpoint
{
    private string             $contentType       = 'application/json';
    protected ?LoggerInterface $log               = null;
    private int                $returnCode        = 200;
    private ?string            $authenticatedUser = null;
    private int                $queryRangeFrom;
    private int                $queryRangeTo;
    private int                $resultRangeFrom;
    private int                $resultRangeTo;
    private int                $resultRangeCount;

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

    public function setQueryRange(int $from, ?int $to = null): void
    {
        $this->queryRangeFrom = $from;
        if ($to !== null) {
            $this->queryRangeTo = $to;
        }
    }

    public function setResultRange(int $from, int $count, ?int $to = null): void
    {
        $this->resultRangeFrom  = $from;
        $this->resultRangeCount = $count;
        if ($to !== null) {
            $this->resultRangeTo = $to;
        }
    }

    protected function getQueryRangeFrom(): ?int
    {
        return $this->queryRangeFrom ?? null;
    }

    protected function getQueryRangeTo(): ?int
    {
        return $this->queryRangeTo ?? null;
    }

    public function setResponseRangeHeaders(): void
    {
        if ($this instanceof RangeInterface) {
            header('Accept-Ranges: '.$this->getRangePrefix());
            $range[] = $this->resultRangeFrom ?? 0;
            $rangeTo = $this->resultRangeTo ?? (!isset($this->resultRangeFrom) ? $this->resultRangeCount - 1 : '');
            if (!empty($rangeTo)) {
                $range[] = $rangeTo;
            }
            header('Content-Range: documents '.implode('-', $range).'/'.$this->resultRangeCount);
        }
    }
}
