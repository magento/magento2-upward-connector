<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\UpwardConnector\Model\System\Message;

class Security implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @inheritdoc
     */
    public function getIdentity()
    {
        return 'security';
    }

    /**
     * @inheritdoc
     */
    public function isDisplayed()
    {
        return false;
    }

    /**
     * Retrieve message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        return __('Security check not applicable to UPWARD');
    }

    /**
     * @inheritdoc
     */
    public function getSeverity()
    {
        return \Magento\Framework\Notification\MessageInterface::SEVERITY_NOTICE;
    }
}
