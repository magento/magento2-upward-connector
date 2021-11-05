<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\DeploymentConfig\Writer as ConfigWriter;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UpwardConnector\Api\UpwardPathManagerInterface;

class UpwardPathManager implements UpwardPathManagerInterface
{
    public const LEGACY_CONFIG_PATH = 'web/upward/path';

    /** @var \Magento\Framework\App\DeploymentConfig\Writer */
    private $configWriter;

    /** @var \Magento\Framework\App\DeploymentConfig */
    private $deploymentConfig;

    /** @var \Magento\Store\Model\StoreManagerInterface */
    private $storeManager;

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    private $scopeConfig;

    /** @var array|null */
    private $pathConfig;

    /**
     * @param \Magento\Framework\App\DeploymentConfig\Writer $configWriter
     * @param \Magento\Framework\App\DeploymentConfig $deploymentConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ConfigWriter $configWriter,
        DeploymentConfig $deploymentConfig,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->configWriter = $configWriter;
        $this->deploymentConfig = $deploymentConfig;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritDoc
     */
    public function getPaths(): array
    {
        if ($this->pathConfig === null) {
            $this->pathConfig = $this->deploymentConfig->get(self::PARAM_PATH_CONFIG) ?? [];
        }

        return $this->pathConfig;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): ?string
    {
        $configuredPaths = $this->getPaths();
        $legacyValue = $this->getLegacyValue();
        if (empty($configuredPaths)) {
            return $legacyValue;
        }

        $storeCode = $this->storeManager->getStore()->getCode();
        if (isset($configuredPaths[self::SCOPE_STORE][$storeCode])) {
            return $configuredPaths[self::SCOPE_STORE][$storeCode];
        }

        $websiteCode = $this->storeManager->getWebsite()->getCode();
        if (isset($configuredPaths[self::SCOPE_WEBSITE][$websiteCode])) {
            return $configuredPaths[self::SCOPE_WEBSITE][$websiteCode];
        }

        return $configuredPaths[self::SCOPE_DEFAULT][self::SCOPE_CODE_DEAULT] ?? $legacyValue;
    }

    /**
     * @inheritDoc
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setPath(
        ?string $path,
        ?string $scopeType = self::SCOPE_DEFAULT,
        ?string $scopeCode = self::SCOPE_CODE_DEAULT
    ): UpwardPathManagerInterface {
        if ($scopeType !== self::SCOPE_DEFAULT && !$this->validateScope($scopeType, $scopeCode)) {
            throw new LocalizedException(__('Scope code unavailable for given type'));
        }

        $configuredPaths = $this->getPaths();
        $configuredPaths[$scopeType][$scopeCode] = $path;

        $this->configWriter->saveConfig(
            [
                ConfigFilePool::APP_ENV => [
                    self::PARAM_PATH_CONFIG => $configuredPaths
                ]
            ],
            true
        );

        return $this;
    }

    /**
     * Ensure scope code is valid
     *
     * @param string $scopeType
     * @param string $scopeCode
     *
     * @return bool
     */
    private function validateScope(string $scopeType, string $scopeCode): bool
    {
        if (!in_array($scopeType, $this->getScopeTypes(), true)) {
            return false;
        }

        $codes = $scopeType === self::SCOPE_WEBSITE ?
            $this->storeManager->getWebsites(false, true) :
            $this->storeManager->getStores(false, true);

        return isset($codes[$scopeCode]);
    }

    /**
     * Get system config path value
     *
     * @return string|null
     */
    private function getLegacyValue(): ?string
    {
        return $this->scopeConfig->getValue(self::LEGACY_CONFIG_PATH);
    }

    /**
     * @inheritDoc
     */
    public function getScopeTypes(): array
    {
        return [self::SCOPE_DEFAULT, self::SCOPE_WEBSITE, self::SCOPE_STORE];
    }
}
