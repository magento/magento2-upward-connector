<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Plugin\Magento\Framework\App;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class AreaList
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Controller or frontname to load from default magento frontend
     */
    const UPWARD_CONFIG_PATH_FRONT_NAMES_TO_SKIP = 'web/upward/front_names_to_skip';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param \Magento\Framework\App\AreaList $subject
     * @param string|null $result
     * @param string $frontName
     *
     * @return string|null
     */
    public function afterGetCodeByFrontName(
        \Magento\Framework\App\AreaList $subject,
        $result,
        $frontName
    ) {

        if ($result != 'frontend') {
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
