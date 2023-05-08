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

/**
 * Class Method
 * @package byteShard\Rest
 */
abstract class Method
{
    const VALID_ALPHA = 'VALID_STRING';
    const VALID_ALPHANUMERIC = 'VALID_STRING';
    const VALID_NUMERIC = 'VALID_STRING';

    /**
     * @var array<string, array<string, string|int>>
     */
    protected array $mandatory_parameters = [];
    protected bool  $debug                = false;

    /**
     * @var array<string, array<string, string|int>>
     */
    protected array $optional_parameters = [];

    private ?RestMethod $restMethod = null;
    private bool        $error      = false;
    /**
     * @var array<string>
     */
    private array $errorMessages = [];
    /** @var array<string> */
    private array $debugMessages = [];
    /** @var array<Parameter> */
    private array $mandatoryParameters = [];
    /** @var array<Parameter> */
    private array $optionalParameters = [];

    /** @phpstan-ignore-next-line */
    abstract public function run(mixed $parameters): null|array|string|object;


    public function setRestMethod(RestMethod $method): void
    {
        $this->restMethod = $method;
    }

    public function getRestMethod(): ?RestMethod
    {
        return $this->restMethod;
    }

    public function validate(object $parameters): bool
    {
        $result = true;
        if (count($this->mandatory_parameters) > 0) {
            trigger_error('Using protected property $mandatory_parameters is deprecated. Please use method addMandatoryParameters instead', E_USER_DEPRECATED);
            foreach ($this->mandatory_parameters as $parameter => $test) {
                if (empty($parameters->{$parameter})) {
                    $this->debug(' - '.$parameter.': not found');
                    $this->addError($parameter);
                    $result = false;
                } else {
                    $result = $result && $this->validateOldParameter($parameters, $parameter, $test);
                }
            }
        }
        if (count($this->optional_parameters) > 0) {
            trigger_error('Using protected property $optional_parameters is deprecated. Please use method addOptionalParameters instead', E_USER_DEPRECATED);
            foreach ($this->optional_parameters as $parameter => $test) {
                if (isset($parameters->{$parameter}) && !empty($parameters->{$parameter})) {
                    $result = $result && $this->validateOldParameter($parameters, $parameter, $test);
                }
            }
        }
        foreach ($this->mandatoryParameters as $parameter) {
            if (!isset($parameters->{$parameter->getName()})) {
                $this->debug(' - '.$parameter.': not found');
                $this->addError('Mandatory parameter '.$parameter.' missing');
                $result = false;
            } else {
                $result = $result && $parameter->validate($parameters->{$parameter->getName()});
            }
        }
        foreach ($this->optionalParameters as $parameter) {
            if (isset($parameters->{$parameter->getName()})) {
                $result = $result && $parameter->validate($parameters->{$parameter->getName()});
            }
        }
        return $result;
    }

    private function addError(string $error): void
    {
        $this->error           = true;
        $this->errorMessages[] = $error;
    }

    public function addMandatoryParameters(Parameter ...$parameters): void
    {
        foreach ($parameters as $parameter) {
            $this->mandatoryParameters[] = $parameter;
        }
    }

    public function addOptionalParameters(Parameter ...$parameters): void
    {
        foreach ($parameters as $parameter) {
            $this->optionalParameters[] = $parameter;
        }
    }

    /**
     * @param object $parameters
     * @param string $parameter
     * @param array<string, string|int> $test
     * @return bool
     */
    private function validateOldParameter(object $parameters, string $parameter, array $test = []): bool
    {
        $result = true;
        $this->debug(' - '.$parameter.': '.$parameters->{$parameter});
        if (!is_string($parameters->{$parameter}) || $parameters->{$parameter} === '' || (isset($test['regex']) && is_string($test['regex']) && !preg_match($test['regex'], $parameters->{$parameter})) || (isset($test['length']) && strlen($parameters->{$parameter}) > $test['length'])) {
            if (isset($test['type'])) {
                switch ($test['type']) {
                    case 'int':
                        if (!is_int($parameters->{$parameter})) {
                            $result = false;
                            $this->addError('Invalid characters in '.$parameter.' value');
                        }
                        break;
                    case 'bool':
                        if (!is_bool($parameters->{$parameter})) {
                            $result = false;
                            $this->addError('Invalid characters in '.$parameter.' value');
                        }
                        break;
                }
            } else {
                $result = false;
                $this->addError('Invalid characters in '.$parameter.' value');
            }
        }
        return $result;
    }

    public function getErrorMessage(): string
    {
        return implode("\n", $this->errorMessages);
    }

    public function hasError(): bool
    {
        return $this->error;
    }

    protected function debug(string $message): void
    {
        if ($this->debug === true) {
            $this->debugMessages[] = $message;
        }
    }

    public function getDebugMessage(): string
    {
        return implode("\n", $this->debugMessages);
    }
}
