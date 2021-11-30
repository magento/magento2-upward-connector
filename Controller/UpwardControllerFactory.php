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
use Magento\UpwardConnector\Api\UpwardPathManagerInterface;
use Magento\UpwardConnector\Resolver\Computed;

class UpwardControllerFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\UpwardConnector\Api\UpwardPathManagerInterface
     */
    private $pathManager;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\UpwardConnector\Api\UpwardPathManagerInterface $pathManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        UpwardPathManagerInterface $pathManager
    ) {
        $this->objectManager = $objectManager;
        $this->pathManager = $pathManager;
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
        $upwardConfig = $this->pathManager->getPath();

        if (empty($upwardConfig)) {
            throw new \RuntimeException('Path to UPWARD configuration file not set.');
        }

        $additionalResolvers = [
            Computed::RESOLVER_TYPE => Computed::class
        ];

        return $this->objectManager->create(
            UpwardController::class,
            compact(
                'request',
                'upwardConfig',
                'additionalResolvers'
            )
        );
    }
}
