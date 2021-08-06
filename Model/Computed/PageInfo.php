<?php

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\Computed;

use Magento\Upward\DefinitionIterator;
use Magento\UpwardConnector\Model\PageType;

class PageInfo implements ComputedInterface
{
    /** @var \Magento\UpwardConnector\Model\PageType */
    private $pageTypeResolver;

    public function __construct(
        PageType $pageTypeResolver
    ) {
        $this->pageTypeResolver = $pageTypeResolver;
    }

    public function resolve(DefinitionIterator $iterator)
    {
        return $this->pageTypeResolver
            ->setContext($iterator->getContext())
            ->getJson();
    }
}
