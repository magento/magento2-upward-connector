<?php

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\DataProvider\UrlRewrite;

use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\UrlRewriteGraphQl\Model\DataProvider\EntityDataProviderInterface;

class PageProvider implements EntityDataProviderInterface
{
    /** @var \Magento\Cms\Api\PageRepositoryInterface */
    private $pageRepository;

    /**
     * @param \Magento\Cms\Api\PageRepositoryInterface $pageRepository
     */
    public function __construct(
        PageRepositoryInterface $pageRepository
    ) {
        $this->pageRepository = $pageRepository;
    }

    public function getData(
        string $entity_type,
        int $id,
        ResolveInfo $info = null,
        int $storeId = null
    ): array {
        $page = $this->pageRepository->getById($id);

        return $page && $page->isActive() ? $page->getData() : [];
    }
}
