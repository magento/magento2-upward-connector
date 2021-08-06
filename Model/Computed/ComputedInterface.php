<?php

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\Computed;

use Magento\Upward\DefinitionIterator;

interface ComputedInterface
{
    /**
     * Resolve value to use in upward config
     *
     * @param \Magento\Upward\DefinitionIterator $iterator
     *
     * @return mixed
     */
    public function resolve(DefinitionIterator $iterator);
}
