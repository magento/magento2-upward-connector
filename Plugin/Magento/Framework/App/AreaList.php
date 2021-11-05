<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Plugin\Magento\Framework\App;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use Magento\UpwardConnector\Api\UpwardPathManagerInterface;

class AreaList
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\UpwardConnector\Api\UpwardPathManagerInterface
     */
    private $pathManager;

    /**
     * Controller or frontname to load from default magento frontend
     */
    const UPWARD_CONFIG_PATH_FRONT_NAMES_TO_SKIP = 'web/upward/front_names_to_skip';

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\UpwardConnector\Api\UpwardPathManagerInterface|null $pathManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ?UpwardPathManagerInterface $pathManager = null
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->pathManager = $pathManager ?: ObjectManager::getInstance()->get(UpwardPathManagerInterface::class);
    }

    /**
     * Add pwa area code by front name
     *
     * @param \Magento\Framework\App\AreaList $subject
     * @param string|null $result
     * @param string $frontName
     *
     * @return string|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetCodeByFrontName(
        \Magento\Framework\App\AreaList $subject,
        $result,
        $frontName
    ) {

        if ($result !== 'frontend') {
            return $result;
        }

        if (!$this->pathManager->getPath()) {
            return $result;
        }

        $frontNamesToSkip = explode(
            "\r\n",
            $this->scopeConfig->getValue(
                self::UPWARD_CONFIG_PATH_FRONT_NAMES_TO_SKIP,
                ScopeInterface::SCOPE_STORE
            ) ?? ''
        );

        if ($frontName && in_array($frontName, $frontNamesToSkip)) {
            return $result;
        }

        return 'pwa';
    }
}
