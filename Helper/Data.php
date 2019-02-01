<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Data extends AbstractHelper
{
    /**
     * Config path where UPWARD config file path is found.
     */
    public const UPWARD_CONFIG_PATH = 'web/upward/path';

    /**
     * @return string
     */
    public function getConfigPath(): string
    {
        return $this->scopeConfig->getValue(static::UPWARD_CONFIG_PATH, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
    }
}
