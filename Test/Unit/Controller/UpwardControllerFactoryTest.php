<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Test\Unit\Controller;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Upward\Controller as UpwardController;
use Magento\UpwardConnector\Api\UpwardPathManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\UpwardConnector\Controller\UpwardControllerFactory;

class UpwardControllerFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var UpwardPathManagerInterface|MockObject
     */
    private $pathManager;

    /**
     * @var ObjectManagerInterface|MockObject
     */
    private $objectManager;

    /**
     * @var UpwardControllerFactory
     */
    private $upwardControllerFactory;

    protected function setUp() : void
    {
        $objectManagerHelper = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->pathManager = $this->createMock(UpwardPathManagerInterface::class);
        $this->upwardControllerFactory = $objectManagerHelper->getObject(UpwardControllerFactory::class, [
            'objectManager' => $this->objectManager,
            'pathManager' => $this->pathManager
        ]);
    }

    public function testCreateWillReturn()
    {
        $request = $this->createMock(RequestInterface::class);
        $upwardControllerMock = $this->createMock(UpwardController::class);
        $upwardConfig = 'upward/config/path';
        $this->pathManager->expects($this->once())
            ->method('getPath')
            ->willReturn($upwardConfig);
        $this->objectManager->expects($this->once())
            ->method('create')
            ->with(UpwardController::class, compact('request', 'upwardConfig'))
            ->willReturn($upwardControllerMock);

        $this->assertSame($upwardControllerMock, $this->upwardControllerFactory->create($request));
    }

    public function testCreateWillThrow()
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $this->pathManager->expects($this->once())
            ->method('getPath')
            ->willReturn(null);
        $this->objectManager->expects($this->never())->method('create');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path to UPWARD configuration file not set.');
        $this->upwardControllerFactory->create($requestMock);
    }
}
