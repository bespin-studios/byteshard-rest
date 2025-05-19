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

namespace byteShard\Rest;

use byteShard\Enum\RestMethod;
use Closure;

class Location
{
    private string $location;

    /**
     * @var array<string, array{class: string, callback: Closure|null}>
     */
    private array $methods = [];

    public function __construct(string $location)
    {
        $this->location = $location;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function isTypeSupported(RestMethod $method): bool
    {
        return array_key_exists($method->value, $this->methods);
    }

    public function getClassName(RestMethod $method): ?string
    {
        if (array_key_exists($method->value, $this->methods)) {
            return $this->methods[$method->value]['class'];
        }
        return null;
    }

    public function getConstructorCallback(RestMethod $method): ?Closure
    {
        if (array_key_exists($method->value, $this->methods) && array_key_exists('callback', $this->methods[$method->value])) {
            return $this->methods[$method->value]['callback'];
        }
        return null;
    }

    public function addMethod(RestMethod $method, string $class, ?Closure $constructorCallback = null): void
    {
        $this->methods[$method->value]['class'] = $class;
        if ($constructorCallback !== null) {
            $this->methods[$method->value]['callback'] = $constructorCallback;
        }
    }
}
