<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Controller;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Upward\Controller as UpwardController;
use Magento\Framework\App\State;

class UpwardControllerFactory
{
    /**
     * Config path where UPWARD config file path is found.
     */
    public const UPWARD_CONFIG_PATH = 'web/upward/path';

    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var State
     */
    private $appState;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param ScopeConfigInterface $scopeConfig
     * @param State $appState
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ScopeConfigInterface $scopeConfig,
        State $appState
    ) {
        $this->objectManager = $objectManager;
        $this->config = $scopeConfig;
        $this->appState = $appState;
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
        $upwardConfig = $this->config->getValue(
            static::UPWARD_CONFIG_PATH,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        $_SERVER['MAGENTO_BACKEND_URL'] = $this->getMagentoBackendUrl();
        $_SERVER['NODE_ENV'] = $this->getMode();

        if (empty($upwardConfig)) {
            throw new \RuntimeException('Path to UPWARD configuration file not set.');
        }

        return $this->objectManager->create(UpwardController::class, compact('request', 'upwardConfig'));
    }

    /**
     * @return string
     */
    public function getMagentoBackendUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
        return "{$protocol}://{$_SERVER['HTTP_HOST']}";
    }

    /**
     * @return mixed
     */
    public function getMode()
    {
        return $this->appState->getMode();
    }
}
