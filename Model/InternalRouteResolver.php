<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Model;

use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewriteGraphQl\Model\Resolver\UrlRewrite\CustomUrlLocatorInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\Store\Api\StoreRepositoryInterface;

class InternalRouteResolver
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlFinderInterface
     */
    private $urlFinder;

    /**
     * @var CustomUrlLocatorInterface
     */
    private $customUrlLocator;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    public function __construct(
        StoreManagerInterface $storeManager,
        UrlFinderInterface $urlFinder,
        CustomUrlLocatorInterface $customUrlLocator,
        StoreRepositoryInterface $storeRepository
    ) {
        $this->storeManager = $storeManager;
        $this->urlFinder = $urlFinder;
        $this->customUrlLocator = $customUrlLocator;
        $this->storeRepository = $storeRepository;
    }

    /**
     * Find a URL rewrite by request path for the specified store.
     *
     * @param string $requestPath
     * @param int $storeId
     * @return UrlRewrite|null
     */
    private function findUrlFromRequestPath(
        string $requestPath,
        int $storeId
    ): ?UrlRewrite
    {
        return $this->urlFinder->findOneByData([
            UrlRewrite::REQUEST_PATH => $requestPath,
            UrlRewrite::STORE_ID     => $storeId
        ]);
    }

    /**
     * Find a URL rewrite by target path for the specified store.
     *
     * @param string $targetPath
     * @param int $storeId
     * @return UrlRewrite|null
     */
    private function findUrlFromTargetPath(
        string $targetPath,
        int $storeId
    ): ?UrlRewrite
    {
        return $this->urlFinder->findOneByData([
            UrlRewrite::TARGET_PATH => $targetPath,
            UrlRewrite::STORE_ID    => $storeId
        ]);
    }

    /**
     * Follow redirect URL rewrites until the final destination is reached.
     *
     * Redirect loops are prevented by tracking previously visited request paths.
     *
     * @param UrlRewrite $urlRewrite
     * @return UrlRewrite
     */
    private function findFinalUrl(UrlRewrite $urlRewrite): UrlRewrite
    {
        $visited = [];
        
        while ($urlRewrite->getRedirectType() > 0) {
            
            $key = sprintf(
                '%d:%s',
                (int)$urlRewrite->getStoreId(),
                $urlRewrite->getRequestPath()
            );
            
            if (isset($visited[$key])) {
                break;
            }
            
            $visited[$key] = true;
            
            $next = $this->findUrlFromRequestPath(
                $urlRewrite->getTargetPath(),
                (int)$urlRewrite->getStoreId()
            );

            if (!$next) {
                break;
            }

            $urlRewrite = $next;
        }

        return $urlRewrite;
    }

    /**
     * Find the first matching URL rewrite across the specified store views.
     *
     * @param string $url
     * @param int[] $storeIds
     * @return UrlRewrite|null
     */
    private function findUrlRewriteAcrossStores(string $url, array $storeIds): ?UrlRewrite
    {
        $validUrlEntity = null;
        foreach ($storeIds as $storeId) {
            $validUrlEntity = $this->urlFinder->findOneByData(
                [
                    UrlRewrite::REQUEST_PATH => $url,
                    UrlRewrite::STORE_ID     => $storeId,
                ]
            );

            if ($validUrlEntity !== null) {
                break;
            }
        }

        return $validUrlEntity;
    }

    /**
     * Retrieve all active store IDs except the current store.
     *
     * @param int $currentStoreId
     * @return int[]
     */
    private function getOtherStoreIds(int $currentStoreId): array
    {
        $stores = $this->storeRepository->getList();
        $otherStores = [];

        foreach ($stores as $store) {
            $storeId = (int) $store->getId();

            if ($storeId === 0 || ($storeId === $currentStoreId) || !$store->getIsActive()) {
                continue;
            }

            $otherStores[] = $storeId;
        }

        return $otherStores;
    }

    /**
     * Resolve the requested URL by looking for the same entity in other store views.
     *
     * If the URL does not exist in the current store, this method searches all
     * other active stores for a matching request path. When a matching entity is
     * found, it attempts to locate the equivalent URL rewrite for the current
     * store and returns redirect information pointing to that URL.
     *
     * @param string $url
     * @param int $currentStoreId
     * @return array|null
     */
    private function resolveAcrossStores(
        string $url,
        int $currentStoreId
    ): ?array {
        $otherStores = $this->getOtherStoreIds($currentStoreId);

        if (empty($otherStores)) {
            return null;
        }

        $validUrlEntity = $this->findUrlRewriteAcrossStores(
            $url,
            $otherStores
        );

        
        if ($validUrlEntity === null) {
            return null;
        }

        $validUrlEntity = $this->findFinalUrl($validUrlEntity);

        $targetStoreView = $this->urlFinder->findOneByData([
            UrlRewrite::ENTITY_ID   => $validUrlEntity->getEntityId(),
            UrlRewrite::ENTITY_TYPE => $validUrlEntity->getEntityType(),
            UrlRewrite::TARGET_PATH => $validUrlEntity->getTargetPath(),
            UrlRewrite::STORE_ID    => $currentStoreId,
        ]);

        if ($targetStoreView === null) {
            return null;
        }

        return [
            'relative_url' => $targetStoreView->getRequestPath(),
            'redirect_code' => 302,
            'type' => $this->sanitizeType($targetStoreView->getEntityType())
        ];
    }

    /**
     * Resolve a storefront URL.
     *
     * @param string $url
     * @return array
     */
    public function resolve(string $url): array
    {
        $url = parse_url($url, PHP_URL_PATH) ?: $url;

        $storeCode = $this->storeManager->getStore()->getCode();

        if (preg_match('#^/' . preg_quote($storeCode, '#') . '/#', $url)) {
            $url = preg_replace(
                '#^/' . preg_quote($storeCode, '#') . '/#',
                '/',
                $url
            );
        }

        if ($url !== '/' && strpos($url, '/') === 0) {
            $url = ltrim($url, '/');
        }

        $customUrl = $this->customUrlLocator->locateUrl($url);

        if ($customUrl) {
            $url = $customUrl;
        }

        $storeId = (int)$this->storeManager->getStore()->getId();

        $redirectType = 0;

        $urlRewrite = $this->findUrlFromRequestPath($url, $storeId);

        if ($urlRewrite) {
            $redirectType = (int)$urlRewrite->getRedirectType();
        } else {
            $urlRewrite = $this->findUrlFromTargetPath($url, $storeId);
        }

        if (!$urlRewrite) {

            $alternateRoute = $this->resolveAcrossStores($url, $storeId);

            if ($alternateRoute !== null) {

                return [
                    'data' => [
                        'route' => $alternateRoute
                    ]
                ];
            }

            return [
                'data' => [
                    'route' => [
                        'relative_url' => $url,
                        'redirect_code' => 404,
                        'type' => 'PWA_404'
                    ]
                ]
            ];
        }

        $finalUrlRewrite = $this->findFinalUrl($urlRewrite);

        $entityId = (int)$finalUrlRewrite->getEntityId();
        $entityType = $finalUrlRewrite->getEntityType();

        if (!$entityId && $finalUrlRewrite->getTargetPath()) {
            $entityRewrite = $this->findUrlFromTargetPath(
                $finalUrlRewrite->getTargetPath(),
                $storeId
            );

            if ($entityRewrite) {
                $entityId = (int)$entityRewrite->getEntityId();
                $entityType = $entityRewrite->getEntityType();
            }
        }

        $relativeUrl = $finalUrlRewrite->getRequestPath();

        return [
            'data' => [
                'route' => [
                    'relative_url' => $relativeUrl,
                    'redirect_code' => $redirectType,
                    'type' => $entityId
                        ? $this->sanitizeType($entityType)
                        : null
                ]
            ]
        ];
    }

    /**
     * Convert the URL rewrite entity type into the GraphQL schema format.
     *
     * @param string $type
     * @return string
     */
    private function sanitizeType(string $type) : string
    {
        return strtoupper(str_replace('-', '_', $type));
    }
}
