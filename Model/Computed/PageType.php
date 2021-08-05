<?php

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\Computed;

use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewriteGraphQl\Model\Resolver\UrlRewrite\CustomUrlLocatorInterface;

class PageType implements ComputedInterface
{
    /** @var \Magento\Store\Model\StoreManagerInterface */
    private $storeManager;

    /** @var \Magento\UrlRewrite\Model\UrlFinderInterface */
    private $urlFinder;

    /** @var \Magento\UrlRewriteGraphQl\Model\Resolver\UrlRewrite\CustomUrlLocatorInterface */
    private $customUrlLocator;

    /** @var \Magento\Framework\Serialize\Serializer\Json */
    private $json;

    /** @var int */
    private $redirectType;

    public function __construct(
        StoreManagerInterface $storeManager,
        UrlFinderInterface $urlFinder,
        CustomUrlLocatorInterface $customUrlLocator,
        Json $json
    ) {
        $this->storeManager = $storeManager;
        $this->urlFinder = $urlFinder;
        $this->customUrlLocator = $customUrlLocator;
        $this->json = $json;
    }

    public function resolve($context)
    {
        // TODO resolve current store (reference graphql context)
        $storeId = (int) $this->storeManager->getStore()->getId();
        $request = $context->get('request')->toArray();
        $urlParts = $request['url'];
        $url = $urlParts['pathname'];
        $result = [];

        if (substr($url, 0, 1) === '/' && $url !== '/') {
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
                // TODO throw different error type
                throw new GraphQlNoSuchEntityException(
                    __('No such entity found with matching URL key: %url', ['url' => $url])
                );
            }

            $result = $resultArray ?: [];
        }

        return $this->json->serialize($result);
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
                $finalCustomUrlRewrite->getRedirectType() == 0
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
            while ($urlRewrite && $urlRewrite->getRedirectType() > 0) {
                $urlRewrite = $this->findUrlFromRequestPath($urlRewrite->getTargetPath(), $storeId);
            }
        } else {
            $urlRewrite = $this->findUrlFromTargetPath($requestPath, $storeId);
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
