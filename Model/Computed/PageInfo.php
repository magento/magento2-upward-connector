<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\Computed;

use Magento\Framework\GraphQl\Query\Uid;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Upward\Definition;
use Magento\Upward\DefinitionIterator;
use Magento\UpwardConnector\Api\ComputedInterface;
use Magento\UpwardConnector\Model\PageType;
use Magento\UrlRewriteGraphQl\Model\DataProvider\EntityDataProviderComposite;

class PageInfo implements ComputedInterface
{
    /** @var \Magento\UpwardConnector\Model\PageType */
    private $pageTypeResolver;

    /** @var \Magento\Store\Model\StoreManagerInterface */
    private $storeManager;

    /** @var \Magento\Framework\Serialize\Serializer\Json */
    private $json;

    /** @var \Magento\UrlRewriteGraphQl\Model\DataProvider\EntityDataProviderComposite */
    private $entityDataProviderComposite;

    /** @var \Magento\Framework\GraphQl\Query\Uid */
    private $uid;

    /**
     * @param \Magento\UpwardConnector\Model\PageType $pageTypeResolver
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     * @param \Magento\UrlRewriteGraphQl\Model\DataProvider\EntityDataProviderComposite $entityDataProviderComposite
     */
    public function __construct(
        PageType $pageTypeResolver,
        StoreManagerInterface $storeManager,
        Json $json,
        EntityDataProviderComposite $entityDataProviderComposite,
        Uid $uid
    ) {
        $this->pageTypeResolver = $pageTypeResolver;
        $this->storeManager = $storeManager;
        $this->json = $json;
        $this->entityDataProviderComposite = $entityDataProviderComposite;
        $this->uid = $uid;
    }

    /**
     * @param \Magento\Upward\DefinitionIterator $iterator
     * @param \Magento\Upward\Definition $definition
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function resolve(DefinitionIterator $iterator, Definition $definition)
    {
        $pageInfo = $this->pageTypeResolver
            ->setContext($iterator->getContext())
            ->getInfo();

        if (!$pageInfo) {
            return '';
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $type = $pageInfo['type'];
        $additionalMap = $this->getAdditionalMap($definition, $type);

        $entityData = $this->isPageInfoComplete($pageInfo, $additionalMap) ?
            $pageInfo :
            $this->entityDataProviderComposite->getData(
                $type,
                (int)$pageInfo['id'],
                null,
                $storeId
            );

        if (empty($entityData)) {
            return '';
        }

        $result = $this->filterData(
            $entityData,
            $additionalMap,
            $type
        );
        $result['redirect_code'] = $pageInfo['redirect_code'];
        $result['relative_url'] = $pageInfo['relative_url'];
        $result['type'] = $type;

        return $this->json->serialize($result);
    }

    /**
     * @param \Magento\Upward\Definition $definition
     * @param string $type
     *
     * @return false|string[]
     */
    public function getAdditionalMap(Definition $definition, string $type)
    {
        $definitionArray = $definition->toArray();
        $additional = $definitionArray['additional'] ?? null;

        if (!$additional) {
            return false;
        }
        $additionalInfo = [];
        $typeCheck = strtolower($type);
        foreach ($additional as $additionalType) {
            if ($additionalType['type'] === $typeCheck) {
                $additionalInfo = explode(',', $additionalType['fetch']);

                break;
            }
        }

        return $additionalInfo;
    }

    /**
     * @param array $pageInfo
     * @param string[]|bool $additionalMap
     *
     * @return bool
     */
    public function isPageInfoComplete($pageInfo, $additionalMap): bool
    {
        $pageInfoHasAllData = true;
        if ($additionalMap) {
            foreach ($additionalMap as $key) {
                if (!isset($pageInfo[$key])) {
                    $pageInfoHasAllData = false;

                    break;
                }
            }
        }

        return $pageInfoHasAllData;
    }

    /**
     * @param array $data
     * @param array|bool $map
     * @param string $type
     *
     * @return array
     */
    public function filterData($data, $map, $type)
    {
        if (!$map || empty($map) || empty($data)) {
            return [];
        }

        $result = [];
        foreach ($map as $valueKey) {
            $result[$valueKey] = $this->getEntityValue($data, $valueKey, $type);
        }

        return $result;
    }

    /**
     * @param array $data
     * @param string $key
     * @param string $type
     *
     * @return string|null
     */
    public function getEntityValue(array $data, string $key, string $type)
    {
        if ($key === '__typename') {
            if ($type === 'PRODUCT') {
                return ucfirst($data['type_id']) . 'Product';
            }

            return $data['type_id'] ? ucfirst($data['type_id']) : ucfirst(strtolower($type));
        }

        if ($key === 'id') {
            return $data['id'] ?? $data['entity_id'];
        }

        if ($key === 'uid' && (isset($data['id']) || isset($data['entity_id']))) {
            return $this->uid->encode($data['id'] ?? $data['entity_id']);
        }

        return $data[$key] ?? null;
    }
}
