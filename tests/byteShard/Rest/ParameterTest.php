<?php

declare(strict_types=1);

namespace byteShard\Rest;

use PHPUnit\Framework\TestCase;

final class ParameterTest extends TestCase
{

    public function testTypeInt()
    {
        $parameter = new Parameter('name', 0, '', Parameter::TYPE_INT);
        $this->assertTrue($parameter->validate(0));
        $this->assertFalse($parameter->validate('foo'));
    }

    public function testTypeBool()
    {
        $parameter = new Parameter('name', 0, '', Parameter::TYPE_BOOL);
        $this->assertTrue($parameter->validate(true));
        $this->assertFalse($parameter->validate('foo'));
    }

    public function testLength()
    {
        $parameter = new Parameter('name', 10);
        $this->assertTrue($parameter->validate('abcd'));
        $this->assertFalse($parameter->validate('abcdefghijkl'));
    }

    public function testRegex()
    {
        $parameter = new Parameter('name', 0, '/^[A-Za-z0-9_.\/-]+$/');
        $this->assertTrue($parameter->validate('abcd_ABC.012/foo-xxx'));
        $this->assertFalse($parameter->validate('abc%!&'));
    }
}
