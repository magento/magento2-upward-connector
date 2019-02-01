<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Controller;

use Magento\UpwardConnector\Helper\Data;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Upward\Controller as UpwardController;

class UpwardControllerFactory
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param Data $configHelper
     */
    public function __construct(ObjectManagerInterface $objectManager, Data $configHelper)
    {
        $this->objectManager = $objectManager;
        $this->helper = $configHelper;
    }

    /**
     * Create new UPWARD PHP controller for Request
     *
     * @param RequestInterface $request
     *
     * @return UpwardController
     */
    public function create(RequestInterface $request): UpwardController
    {
        $upwardConfig = $this->helper->getConfigPath();

        if (empty($upwardConfig)) {
            throw new \RuntimeException('Path to UPWARD configuration file not set.');
        }

        return $this->objectManager->create(UpwardController::class, compact('request', 'upwardConfig'));
    }
}
