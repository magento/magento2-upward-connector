<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Controller;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Upward\Controller as UpwardController;

class UpwardControllerFactory
{
    /**
     * ENV key where config file path is found.
     */
    public const VAR_UPWARD_CONFIG = 'UPWARD_PHP_UPWARD_CONFIG';

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
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
        $upwardConfig = getenv(static::VAR_UPWARD_CONFIG);

        if (empty($upwardConfig)) {
            throw new \RuntimeException('Environment variable ' . static::VAR_UPWARD_CONFIG . 'not set.');
        }

        return $this->objectManager->create(UpwardController::class, compact('request', 'upwardConfig'));
    }
}
