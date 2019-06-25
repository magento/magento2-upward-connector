<?php

namespace Magento\UpwardConnector\Plugin\Magento\Framework\App;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class AreaList
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Enable Pwa frontend for a certain storeview
     */
    public const UPWARD_CONFIG_PATH_ENABLED = 'web/upward/enabled';

    /**
     * Controller or frontname to load from default magento frontend
     */
    public const UPWARD_CONFIG_PATH_FRONT_NAMES_TO_SKIP = 'web/upward/front_names_to_skip';

    /**
     * AreaList constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param \Magento\Framework\App\AreaList $subject
     * @param $result
     * @param $frontName
     * @return string
     */
    public function afterGetCodeByFrontName(
        \Magento\Framework\App\AreaList $subject,
        $result,
        $frontName
    ) {
        $frontNamesToSkip = explode(
            '\n',
            $this->scopeConfig->getValue(
                self::UPWARD_CONFIG_PATH_FRONT_NAMES_TO_SKIP,
                ScopeInterface::SCOPE_STORE
            )
        );

        if ($result == 'frontend' && in_array($frontName, $frontNamesToSkip)) {
            return $result;
        }

        if ($result == 'frontend' &&
            $this->scopeConfig->getValue(
                self::UPWARD_CONFIG_PATH_ENABLED,
                ScopeInterface::SCOPE_STORE
            )
        ) {
            return 'pwa';
        }

        return $result;
    }
}