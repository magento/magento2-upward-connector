<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Model;

use Magento\Framework\Exception\RuntimeException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewriteGraphQl\Model\Resolver\UrlRewrite\CustomUrlLocatorInterface;
use Magento\Upward\Context;

class PageType
{
    /** @var \Magento\Store\Model\StoreManagerInterface */
    private $storeManager;

    /** @var \Magento\UrlRewrite\Model\UrlFinderInterface */
    private $urlFinder;

    /** @var \Magento\UrlRewriteGraphQl\Model\Resolver\UrlRewrite\CustomUrlLocatorInterface */
    private $customUrlLocator;

    /** @var int */
    private $redirectType;

    /** @var \Magento\Upward\Context */
    private $context;

    /** @var array|null */
    private $pageType;

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\UrlRewrite\Model\UrlFinderInterface $urlFinder
     * @param \Magento\UrlRewriteGraphQl\Model\Resolver\UrlRewrite\CustomUrlLocatorInterface $customUrlLocator
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        UrlFinderInterface $urlFinder,
        CustomUrlLocatorInterface $customUrlLocator
    ) {
        $this->storeManager = $storeManager;
        $this->urlFinder = $urlFinder;
        $this->customUrlLocator = $customUrlLocator;
    }

    /**
     * Get page information
     *
     * @return array|string[]|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getInfo(): ?array
    {
        if ($this->pageType === null) {
            $this->pageType = $this->resolvePageInfo();
        }

        return $this->pageType;
    }

    /**
     * Get the page type
     *
     * @return string|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPageType(): ?string
    {
        $pageInfo = $this->getInfo();

        return $pageInfo['type'] ?? null;
    }

    /**
     * Set the UPWARD Context
     *
     * @param \Magento\Upward\Context $context
     * @return $this
     */
    public function setContext(Context $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get UPWARD Context
     *
     * @return \Magento\Upward\Context|null
     */
    public function getContext(): ?Context
    {
        return $this->context;
    }

    /**
     * Resolve page information
     *
     * @return array<string, string>|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function resolvePageInfo(): ?array
    {
        if (!$this->getContext()) {
            throw new RuntimeException(__('UPWARD Context not set'));
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $request = $this->getContext()->get('request')->toArray();
        $urlParts = $request['url'];
        $url = $urlParts['pathname'];
        $result = null;

        $url = preg_replace('/^\/' . $this->storeManager->getStore()->getCode() . '\//', '/', $url);

        if ($url !== '/' && strpos($url, '/') === 0) {
            $url = ltrim($url, '/');
        }

        $this->redirectType = 0;
        $customUrl = $this->customUrlLocator->locateUrl($url);
        $url = $customUrl ?: $url;
        $finalUrlRewrite = $this->findFinalUrl($url, $storeId);
        if ($finalUrlRewrite) {
            $relativeUrl = $finalUrlRewrite->getRequestPath();
            $resultArray = $this->rewriteCustomUrls($finalUrlRewrite, $storeId) ?? [
                    'id' => $finalUrlRewrite->getEntityId(),
                    'canonical_url' => $relativeUrl,
                    'relative_url' => $relativeUrl,
                    'redirectCode' => $this->redirectType,
                    'redirect_code' => $this->redirectType,
                    'type' => $this->sanitizeType($finalUrlRewrite->getEntityType())
                ];
            if (!empty($urlParts['search'])) {
                $resultArray['relative_url'] .= $urlParts['search'];
            }

            if (empty($resultArray['id'])) {
                throw new RuntimeException(
                    __('No such entity found with matching URL key: %url', ['url' => $url])
                );
            }

            $result = $resultArray ?: null;
        }

        return $result;
    }


    /**
     * Handle custom urls with and without redirects
     *
     * @param UrlRewrite $finalUrlRewrite
     * @param int $storeId
     * @return array|null
     */
    private function rewriteCustomUrls(UrlRewrite $finalUrlRewrite, int $storeId): ?array
    {
        if ($finalUrlRewrite->getEntityType() === 'custom' || !($finalUrlRewrite->getEntityId() > 0)) {
            $finalCustomUrlRewrite = clone $finalUrlRewrite;
            $finalUrlRewrite = $this->findFinalUrl($finalCustomUrlRewrite->getTargetPath(), $storeId, true);
            $relativeUrl =
                (int) $finalCustomUrlRewrite->getRedirectType() === 0
                    ? $finalCustomUrlRewrite->getRequestPath() : $finalUrlRewrite->getRequestPath();
            return [
                'id' => $finalUrlRewrite->getEntityId(),
                'canonical_url' => $relativeUrl,
                'relative_url' => $relativeUrl,
                'redirectCode' => $finalCustomUrlRewrite->getRedirectType(),
                'redirect_code' => $finalCustomUrlRewrite->getRedirectType(),
                'type' => $this->sanitizeType($finalUrlRewrite->getEntityType())
            ];
        }
        return null;
    }

    /**
     * Find the final url passing through all redirects if any
     *
     * @param string $requestPath
     * @param int $storeId
     * @param bool $findCustom
     * @return UrlRewrite|null
     */
    private function findFinalUrl(string $requestPath, int $storeId, bool $findCustom = false): ?UrlRewrite
    {
        $urlRewrite = $this->findUrlFromRequestPath($requestPath, $storeId);
        if ($urlRewrite) {
            $this->redirectType = $urlRewrite->getRedirectType();
            if($urlRewrite->getRedirectType() > 0) {
                while ($urlRewrite && $urlRewrite->getRedirectType() > 0) {
                    $urlRewrite = $this->findUrlFromRequestPath($urlRewrite->getTargetPath(), $storeId);
                }
            } else {
                $urlRewrite = $this->findUrlFromTargetPath($requestPath, $storeId);
            }
        }
        if ($urlRewrite && ($findCustom && !$urlRewrite->getEntityId() && !$urlRewrite->getIsAutogenerated())) {
            $urlRewrite = $this->findUrlFromTargetPath($urlRewrite->getTargetPath(), $storeId);
        }

        return $urlRewrite;
    }

    /**
     * Find a url from a request url on the current store
     *
     * @param string $requestPath
     * @param int $storeId
     * @return UrlRewrite|null
     */
    private function findUrlFromRequestPath(string $requestPath, int $storeId): ?UrlRewrite
    {
        return $this->urlFinder->findOneByData(
            [
                'request_path' => $requestPath,
                'store_id' => $storeId
            ]
        );
    }

    /**
     * Find a url from a target url on the current store
     *
     * @param string $targetPath
     * @param int $storeId
     * @return UrlRewrite|null
     */
    private function findUrlFromTargetPath(string $targetPath, int $storeId): ?UrlRewrite
    {
        return $this->urlFinder->findOneByData(
            [
                'target_path' => $targetPath,
                'store_id' => $storeId
            ]
        );
    }

    /**
     * Sanitize the type to fit schema specifications
     *
     * @param string $type
     * @return string
     */
    private function sanitizeType(string $type) : string
    {
        return strtoupper(str_replace('-', '_', $type));
    }
}
