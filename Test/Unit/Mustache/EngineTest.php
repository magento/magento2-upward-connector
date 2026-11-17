<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Test\Unit\Mustache;

use Magento\UpwardConnector\Mustache\Engine;
use PHPUnit\Framework\TestCase;

class EngineTest extends TestCase
{
    public function testAcceptsTraversableHelpers(): void
    {
        $engine = new Engine();
        $engine->setHelpers(new \ArrayIterator(['greeting' => 'Hello']));

        $this->assertSame('Hello', $engine->getHelper('greeting'));
    }

}
