<?php

namespace byteShard\Rest;

interface RangeInterface
{
    public function getRangePrefix(): string;
    
    public function setRange(int $from, ?int $to = null): void;
}
