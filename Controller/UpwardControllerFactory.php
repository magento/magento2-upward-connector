<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Controller;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Upward\Controller as UpwardController;

class UpwardControllerFactory
{
    /**
     * Deployment config path where UPWARD config file path is found.
     */
    public const UPWARD_CONFIG_PATH = 'upward/path';

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(ObjectManagerInterface $objectManager, DeploymentConfig $deploymentConfig)
    {
        $this->objectManager = $objectManager;
        $this->deploymentConfig = $deploymentConfig;
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
        $upwardConfig = $this->deploymentConfig->get(self::UPWARD_CONFIG_PATH);

        if (empty($upwardConfig)) {
            throw new \RuntimeException('Environment variable ' . self::UPWARD_CONFIG_PATH . ' not set.');
        }

        return $this->objectManager->create(UpwardController::class, compact('request', 'upwardConfig'));
    }
}
