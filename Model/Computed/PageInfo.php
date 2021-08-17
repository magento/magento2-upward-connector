<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\Computed;

use Magento\Upward\DefinitionIterator;
use Magento\UpwardConnector\Api\ComputedInterface;
use Magento\UpwardConnector\Model\PageType;

class PageInfo implements ComputedInterface
{
    /** @var \Magento\UpwardConnector\Model\PageType */
    private $pageTypeResolver;

    /**
     * @param \Magento\UpwardConnector\Model\PageType $pageTypeResolver
     */
    public function __construct(
        PageType $pageTypeResolver
    ) {
        $this->pageTypeResolver = $pageTypeResolver;
    }

    /**
     * @param \Magento\Upward\DefinitionIterator $iterator
     *
     * @return string
     */
    public function resolve(DefinitionIterator $iterator)
    {
        return $this->pageTypeResolver
            ->setContext($iterator->getContext())
            ->getJson();
    }
}
