<?php

namespace byteShard\Rest;

interface RangeInterface
{
    public function getRangePrefix(): string;
    
    public function setQueryRange(int $from, ?int $to = null): void;
}
