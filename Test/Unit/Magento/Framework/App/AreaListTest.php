<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Test\Unit\Magento\Framework\App;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\UpwardConnector\Api\UpwardPathManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Framework\App\AreaList;
use Magento\UpwardConnector\Plugin\Magento\Framework\App\AreaList as AreaListPlugin;

class AreaListTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfig;

    /**
     * @var AreaList|MockObject
     */
    private $areaListMock;

    /**
     * @var AreaListPlugin
     */
    private $areaListPlugin;

    /**
     * @var UpwardPathManagerInterface|MockObject
     */
    private $pathManager;

    protected function setUp() : void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->areaListMock = $this->createMock(\Magento\Framework\App\AreaList::class);

        $this->pathManager = $this->createMock(UpwardPathManagerInterface::class);
        $this->scopeConfig->expects($this->any())->method('getValue')->willReturn(
            "foo\r\nbar"
        );

        $objectManagerHelper = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->areaListPlugin = $objectManagerHelper->getObject(
            AreaListPlugin::class,
            [
                'scopeConfig' => $this->scopeConfig,
                'pathManager' => $this->pathManager
            ]
        );
    }

    /**
     * @param string $resultParam
     * @param string $frontNameParam
     * @param string $expected
     *
     * @dataProvider afterGetCodeByFrontNameDataProvider
     */
    public function testAfterGetCodeByFrontName(
        string $resultParam,
        string $frontNameParam,
        string $expected,
        ?string $upwardConfig
    ) {
        if ($resultParam === 'frontend') {
            $this->pathManager->expects($this->once())
                ->method('getPath')
                ->willReturn($upwardConfig);
        }

        $result = $this->areaListPlugin->afterGetCodeByFrontName(
            $this->areaListMock,
            $resultParam,
            $frontNameParam
        );

        $this->assertEquals($expected, $result);
    }

    /**
     * @return []
     */
    public function afterGetCodeByFrontNameDataProvider()
    {
        $upwardConfig = 'upward/config/path';

        return [
            'Adminhtml area passes through' => [
                'adminhtml',
                '',
                'adminhtml',
                $upwardConfig
            ],
            'Frontend area w/o frontname goes to UPWARD' => [
                'frontend',
                '',
                'pwa',
                $upwardConfig
            ],
            'Frontend area w/o frontname in allow list goes to UPWARD' => [
                'frontend',
                'baz',
                'pwa',
                $upwardConfig
            ],
            'Frontend area with frontname in allow list passes through' => [
                'frontend',
                'foo',
                'frontend',
                $upwardConfig
            ],
            'UPWARD path not configured passes through' => [
                'frontend',
                '',
                'frontend',
                null
            ],
        ];
    }
}
