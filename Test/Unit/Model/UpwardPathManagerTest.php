<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Test\Unit\Controller;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\DeploymentConfig\Writer as ConfigWriter;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UpwardConnector\Api\UpwardPathManagerInterface;
use Magento\UpwardConnector\Model\UpwardPathManager;
use PHPUnit\Framework\MockObject\MockObject;

class UpwardPathManagerTest extends \PHPUnit\Framework\TestCase
{
    private const WEBSITE_CODE = 'base';
    private const STORE_CODE = 'en';

    /** @var ConfigWriter|MockObject  */
    private $configWriter;

    /** @var DeploymentConfig|MockObject */
    private $deploymentConfig;

    /** @var StoreManagerInterface|MockObject */
    private $storeManager;

    /** @var ScopeConfigInterface|MockObject */
    private $scopeConfig;

    /**
     * @var UpwardPathManager
     */
    private $pathManager;

    protected function setUp() : void
    {
        $objectManagerHelper = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);

        $this->configWriter = $this->createMock(ConfigWriter::class);
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $this->pathManager = $objectManagerHelper->getObject(UpwardPathManager::class, [
            'configWriter' => $this->configWriter,
            'deploymentConfig' => $this->deploymentConfig,
            'storeManager' => $this->storeManager,
            'scopeConfig' => $this->scopeConfig
        ]);
    }

    public function testHandlesEmptyConfiguration()
    {
        $this->deploymentConfig->expects($this->once())
            ->method('get')
            ->with(UpwardPathManagerInterface::PARAM_PATH_CONFIG)
            ->willReturn(null);

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(UpwardPathManager::LEGACY_CONFIG_PATH)
            ->willReturn(null);

        $this->assertSame(null, $this->pathManager->getPath());
    }

    public function testReturnsStoreValue()
    {
        $upwardPath = '/website/path/to/upward';
        $this->deploymentConfig->expects($this->once())
            ->method('get')
            ->with(UpwardPathManagerInterface::PARAM_PATH_CONFIG)
            ->willReturn([
                UpwardPathManagerInterface::SCOPE_DEFAULT => [
                    UpwardPathManagerInterface::SCOPE_CODE_DEAULT => '/default/path/to/upward'
                ],
                UpwardPathManagerInterface::SCOPE_WEBSITE => [
                    self::WEBSITE_CODE => $upwardPath
                ],
                UpwardPathManagerInterface::SCOPE_STORE => [
                    self::STORE_CODE => $upwardPath
                ]
            ]);

        $this->mockStoreManager();

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(UpwardPathManager::LEGACY_CONFIG_PATH)
            ->willReturn(null);

        $this->assertSame($upwardPath, $this->pathManager->getPath());
    }

    public function testFallsbackToWebsite()
    {
        $upwardPath = '/website/path/to/upward';
        $this->deploymentConfig->expects($this->once())
            ->method('get')
            ->with(UpwardPathManagerInterface::PARAM_PATH_CONFIG)
            ->willReturn([
                UpwardPathManagerInterface::SCOPE_DEFAULT => [
                    UpwardPathManagerInterface::SCOPE_CODE_DEAULT => '/default/path/to/upward'
                ],
                UpwardPathManagerInterface::SCOPE_WEBSITE => [
                    self::WEBSITE_CODE => $upwardPath
                ]
            ]);

        $this->mockStoreManager();

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(UpwardPathManager::LEGACY_CONFIG_PATH)
            ->willReturn(null);

        $this->assertSame($upwardPath, $this->pathManager->getPath());
    }

    public function testFallsbackToDefault()
    {
        $upwardPath = '/default/path/to/upward';
        $this->deploymentConfig->expects($this->once())
            ->method('get')
            ->with(UpwardPathManagerInterface::PARAM_PATH_CONFIG)
            ->willReturn([
                UpwardPathManagerInterface::SCOPE_DEFAULT => [
                    UpwardPathManagerInterface::SCOPE_CODE_DEAULT => $upwardPath
                ]
            ]);

        $this->mockStoreManager();

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(UpwardPathManager::LEGACY_CONFIG_PATH)
            ->willReturn(null);

        $this->assertSame($upwardPath, $this->pathManager->getPath());
    }

    public function testFallsbackToLegacyConfiguration()
    {
        $upwardPath = '/legacy/path/to/upward';
        $this->deploymentConfig->expects($this->once())
            ->method('get')
            ->with(UpwardPathManagerInterface::PARAM_PATH_CONFIG)
            ->willReturn(null);

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(UpwardPathManager::LEGACY_CONFIG_PATH)
            ->willReturn($upwardPath);

        $this->assertSame($upwardPath, $this->pathManager->getPath());
    }

    public function testWrittingConfiguration()
    {
        $pathToWrite = '/some/path';
        $this->deploymentConfig->expects($this->once())
            ->method('get')
            ->with(UpwardPathManagerInterface::PARAM_PATH_CONFIG)
            ->willReturn(null);
        $this->mockStoreManager();

        $this->storeManager->expects($this->once())
            ->method('getStores')
            ->willReturn([
                self::STORE_CODE => 'mock'
            ]);

        $writeArguments = null;
        $this->configWriter->expects($this->once())
            ->method('saveConfig')
            ->willReturnCallback(function ($config) use (&$writeArguments) {
                $writeArguments = $config;
            });

        $this->pathManager->setPath(
            $pathToWrite,
            UpwardPathManagerInterface::SCOPE_STORE,
            self::STORE_CODE
        );

        $this->assertSame(
            [
                ConfigFilePool::APP_ENV => [
                    UpwardPathManagerInterface::PARAM_PATH_CONFIG => [
                        UpwardPathManagerInterface::SCOPE_STORE => [
                            self::STORE_CODE => $pathToWrite
                        ]
                    ]
                ]
            ],
            $writeArguments
        );
    }

    public function testWrittingInvalidStoreWillThrow()
    {
        $this->storeManager
            ->expects($this->once())
            ->method('getWebsites')
            ->willReturn([
                self::WEBSITE_CODE => 'mock'
            ]);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Scope code unavailable for given type');
        $this->pathManager->setPath('/some/path', UpwardPathManagerInterface::SCOPE_WEBSITE, 'foo');
    }

    private function mockStoreManager()
    {
        $mockStore = $this->createMock(StoreInterface::class);
        $mockStore->method('getCode')->willReturn(self::STORE_CODE);

        $mockWebsite = $this->createMock(WebsiteInterface::class);
        $mockWebsite->method('getCode')->willReturn(self::WEBSITE_CODE);

        $this->storeManager->expects($this->any())
            ->method('getStore')
            ->willReturn($mockStore);

        $this->storeManager->expects($this->any())
            ->method('getWebsite')
            ->willReturn($mockWebsite);
    }
}
