<?php

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\Computed;

use Magento\Upward\Context;

interface ComputedInterface
{
    /**
     * Resolve value to use in upward config
     *
     * @param \Magento\Upward\Context $context
     *
     * @return mixed
     */
    public function resolve(Context $context);
}
