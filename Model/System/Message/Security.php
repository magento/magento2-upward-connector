<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\UpwardConnector\Model\System\Message;

use Magento\Store\Model\Store;

class Security implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @return string
     */
    public function getIdentity()
    {
        return 'security';
    }

    /**
     * @return bool
     */
    public function isDisplayed()
    {
        return false;
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        return __('Security check not applicable to UPWARD');
    }

    /**
     * @return int
     */
    public function getSeverity()
    {
        return \Magento\Framework\Notification\MessageInterface::SEVERITY_NOTICE;
    }
}
