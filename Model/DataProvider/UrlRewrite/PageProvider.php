<?php

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\DataProvider\UrlRewrite;

use Magento\Catalog\Model\CategoryRepository;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\UrlRewriteGraphQl\Model\DataProvider\EntityDataProviderInterface;
use Magento\Widget\Model\Template\FilterEmulate;

class PageProvider implements EntityDataProviderInterface
{

    private $pageRepository;

    /**
     * @param PageRepositoryInterface $pageRepository
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
