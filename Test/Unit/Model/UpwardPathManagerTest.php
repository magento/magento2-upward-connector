<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Test\Unit\Model;

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

    protected function setUp() : void
    {
        $this->configWriter = $this->createStub(ConfigWriter::class);
        $this->deploymentConfig = $this->createStub(DeploymentConfig::class);
        $this->storeManager = $this->createStub(StoreManagerInterface::class);
        $this->scopeConfig = $this->createStub(ScopeConfigInterface::class);
    }

    private function getPathManager(): UpwardPathManager
    {
        return new UpwardPathManager(
            $this->configWriter,
            $this->deploymentConfig,
            $this->storeManager,
            $this->scopeConfig
        );
    }

    public function testHandlesEmptyConfiguration()
    {
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->deploymentConfig->expects($this->once())
            ->method('get')
            ->with(UpwardPathManagerInterface::PARAM_PATH_CONFIG)
            ->willReturn(null);

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(UpwardPathManager::LEGACY_CONFIG_PATH)
            ->willReturn(null);

        $this->assertSame(null, $this->getPathManager()->getPath());
    }

    public function testReturnsStoreValue()
    {
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
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

        $this->assertSame($upwardPath, $this->getPathManager()->getPath());
    }

    public function testFallsbackToWebsite()
    {
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
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

        $this->assertSame($upwardPath, $this->getPathManager()->getPath());
    }

    public function testFallsbackToDefault()
    {
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
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

        $this->assertSame($upwardPath, $this->getPathManager()->getPath());
    }

    public function testFallsbackToLegacyConfiguration()
    {
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $upwardPath = '/legacy/path/to/upward';
        $this->deploymentConfig->expects($this->once())
            ->method('get')
            ->with(UpwardPathManagerInterface::PARAM_PATH_CONFIG)
            ->willReturn(null);

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(UpwardPathManager::LEGACY_CONFIG_PATH)
            ->willReturn($upwardPath);

        $this->assertSame($upwardPath, $this->getPathManager()->getPath());
    }

    public function testWrittingConfiguration()
    {
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->configWriter = $this->createMock(ConfigWriter::class);
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

        $this->getPathManager()->setPath(
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
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager
            ->expects($this->once())
            ->method('getWebsites')
            ->willReturn([
                self::WEBSITE_CODE => 'mock'
            ]);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Scope code unavailable for given type');
        $this->getPathManager()->setPath('/some/path', UpwardPathManagerInterface::SCOPE_WEBSITE, 'foo');
    }

    private function mockStoreManager()
    {
        $mockStore = $this->createStub(StoreInterface::class);
        $mockStore->method('getCode')->willReturn(self::STORE_CODE);

        $mockWebsite = $this->createStub(WebsiteInterface::class);
        $mockWebsite->method('getCode')->willReturn(self::WEBSITE_CODE);

        $this->storeManager->method('getStore')
            ->willReturn($mockStore);

        $this->storeManager->method('getWebsite')
            ->willReturn($mockWebsite);
    }
}
