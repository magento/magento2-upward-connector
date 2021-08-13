<?php

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\DataProvider\UrlRewrite;

use Magento\Catalog\Model\CategoryRepository;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\UrlRewriteGraphQl\Model\DataProvider\EntityDataProviderInterface;

class CatalogDataProvider implements EntityDataProviderInterface
{
    /** @var \Magento\Catalog\Model\CategoryRepository */
    private $categoryRepository;

    public function __construct(
        CategoryRepository $categoryRepository
    ) {
        $this->categoryRepository = $categoryRepository;
    }

    public function getData(
        string $entity_type,
        int $id,
        ResolveInfo $info = null,
        int $storeId = null
    ): array {
        $category = $this->categoryRepository->get($id, $storeId);

        return $category->getData();
    }
}
