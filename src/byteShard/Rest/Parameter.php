<?php

namespace byteShard\Rest;

class Parameter
{
    const TYPE_BOOL = 'bool';
    const TYPE_INT = 'int';
    private string $name;
    private int    $maxLength;
    private string $regex;
    private string $type;

    public function __construct(string $name, int $maxLength = 0, string $regex = '', string $type = '')
    {
        $this->name      = $name;
        $this->maxLength = $maxLength;
        $this->regex     = $regex;
        $this->type      = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function validate(string|int|bool $value): bool
    {
        if ($this->maxLength > 0 && is_string($value)) {
            if (strlen($value) > $this->maxLength) {
                return false;
            }
        }
        if ($this->regex !== '' && is_string($value)) {
            if (!preg_match($this->regex, $value)) {
                return false;
            }
        }
        if ($this->type !== '') {
            switch ($this->type) {
                case self::TYPE_BOOL:
                    if (!is_bool($value)) {
                        return false;
                    }
                    break;
                case self::TYPE_INT:
                    if (!is_int($value)) {
                        return false;
                    }
                    break;
            }
        }
        return true;
    }

    public function __toString()
    {
        return $this->name;
    }
}