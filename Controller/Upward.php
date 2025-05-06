<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Controller;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\HTTP\PhpEnvironment\Response;
use Laminas\Http\Response\Stream;
use Magento\UpwardConnector\Model\Prerender;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Upward implements FrontControllerInterface
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var UpwardControllerFactory
     */
    private $upwardFactory;

    /**
     * @var Prerender
     */
    private $prerender;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $url;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Upward constructor.
     * @param Response $response
     * @param UpwardControllerFactory $upwardFactory
     * @param Prerender $prerender
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $url
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Response $response,
        UpwardControllerFactory $upwardFactory,
        Prerender $prerender,
        StoreManagerInterface $storeManager,
        UrlInterface $url,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->response = $response;
        $this->upwardFactory = $upwardFactory;
        $this->prerender = $prerender;
        $this->storeManager = $storeManager;
        $this->url = $url;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Dispatch application action
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function dispatch(RequestInterface $request)
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(
            UrlInterface::URL_TYPE_WEB,
            $this->storeManager->getStore()->isCurrentlySecure()
        );
        // phpcs:disable Magento2.Functions.DiscouragedFunction
        $uri = parse_url($baseUrl);
        if ($request->getUri()->getHost() !== $uri['host']) {
            $redirectUrl = $this->url->getRedirectUrl(
                $this->url->getDirectUrl(ltrim($request->getPathInfo(), '/'), ['_nosid' => true])
            );
            $redirectCode = (int)$this->scopeConfig->getValue(
                'web/url/redirect_to_base',
                ScopeInterface::SCOPE_STORE
            ) !== 301 ? 302 : 301;
            $this->response->setRedirect($redirectUrl, $redirectCode);
            return $this->response;
        }

        $prerenderedResponse = null;
        if ($this->prerender->shouldShowPrerenderedPage($request)) {
            /** @var \Laminas\Http\Response $prerenderedResponse */
            $prerenderedResponse = $this->prerender->getPrerenderedPageResponse($request);
        }

        /** @var \Laminas\Http\Response $upwardResponse */
        $upwardResponse = $prerenderedResponse ? $prerenderedResponse : $this->upwardFactory->create($request)();
        $content = $upwardResponse instanceof Stream ? $upwardResponse->getBody() : $upwardResponse->getContent();

        $this->response->setHeaders($upwardResponse->getHeaders());
        $this->response->setStatusCode($upwardResponse->getStatusCode());
        $this->response->setContent($content);

        return $this->response;
    }
}
